<?php

namespace App\Support;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Résout la matrice rôle → permissions en combinant la table éditable
 * (role_permissions) et le défaut de config/profils.php.
 *
 * Règle : un profil qui possède des lignes en base est « personnalisé » et ses
 * lignes font foi ; sinon il hérite de la matrice de config. Le SUPER_ADMIN a
 * toujours TOUTES les permissions (accès implicite via Gate::before).
 *
 * Le résultat est mis en cache (une lecture BDD par cycle) et purgé à chaque
 * écriture. Si la table n'existe pas encore (migrations non jouées), on retombe
 * silencieusement sur la config : l'application reste fonctionnelle.
 */
class PermissionRepository
{
    private const CACHE_KEY = 'rbac.matrice';

    /** Clés réservées au SUPER_ADMIN : jamais attribuables à un autre profil. */
    public const NON_ASSIGNABLES = [
        Permissions::SUPERADMINS_GERER,
        Permissions::PARAMETRES_GERER,
        Permissions::ROLES_GERER,
    ];

    /**
     * Matrice effective (hors SUPER_ADMIN), profil => permissions[].
     *
     * @return array<string,string[]>
     */
    public function matrice(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->construire());
    }

    /**
     * Permissions effectives d'un profil (SUPER_ADMIN => toutes).
     *
     * @return string[]
     */
    public function pour(string $profil): array
    {
        if ($profil === Permissions::SUPER_ADMIN) {
            return Permissions::TOUTES;
        }

        return $this->matrice()[$profil] ?? $this->defaut($profil);
    }

    /**
     * Remplace intégralement les permissions d'un profil et renvoie l'effectif.
     * La validation métier (clés connues, non réservées, séparation des tâches)
     * est faite en amont par le contrôleur.
     *
     * @param  string[]  $permissions
     * @return string[]
     */
    public function remplacer(string $profil, array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));

        DB::transaction(function () use ($profil, $permissions) {
            RolePermission::where('role', $profil)->delete();

            $lignes = array_map(fn (string $p) => [
                'role' => $profil,
                'permission' => $p,
                'created_at' => now(),
                'updated_at' => now(),
            ], $permissions);

            if ($lignes !== []) {
                RolePermission::insert($lignes);
            }
        });

        $this->vider();

        return $this->pour($profil);
    }

    /**
     * Rétablit le défaut de config pour un profil (supprime sa personnalisation).
     *
     * @return string[]
     */
    public function reinitialiser(string $profil): array
    {
        RolePermission::where('role', $profil)->delete();
        $this->vider();

        return $this->pour($profil);
    }

    /**
     * Profils actuellement personnalisés (au moins une ligne en base).
     *
     * @return string[]
     */
    public function personnalises(): array
    {
        if (! $this->tableDisponible()) {
            return [];
        }

        return RolePermission::query()->distinct()->orderBy('role')->pluck('role')->all();
    }

    public function vider(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string,string[]>
     */
    private function construire(): array
    {
        $overrides = $this->overrides();
        $matrice = [];

        foreach (Permissions::PROFILS as $profil) {
            if ($profil === Permissions::SUPER_ADMIN) {
                continue;
            }
            $matrice[$profil] = $overrides[$profil] ?? $this->defaut($profil);
        }

        return $matrice;
    }

    /**
     * Défaut immuable d'un profil : config/profils.php, repli sur la constante.
     *
     * @return string[]
     */
    private function defaut(string $profil): array
    {
        return config("profils.matrice.$profil")
            ?? Permissions::MATRICE[$profil]
            ?? [];
    }

    /**
     * Personnalisations en base, profil => permissions[].
     *
     * @return array<string,string[]>
     */
    private function overrides(): array
    {
        if (! $this->tableDisponible()) {
            return [];
        }

        return RolePermission::query()
            ->get(['role', 'permission'])
            ->groupBy('role')
            ->map(fn ($lignes) => $lignes->pluck('permission')->all())
            ->all();
    }

    private function tableDisponible(): bool
    {
        return Schema::hasTable('role_permissions');
    }
}
