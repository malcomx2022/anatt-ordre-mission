<?php

namespace App\Support;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Support\Collection;
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
     * Permissions par défaut (config) d'un profil, sans rien écrire : permet de
     * contrôler une réinitialisation AVANT de l'appliquer.
     *
     * @return string[]
     */
    public function parDefaut(string $profil): array
    {
        return $this->defaut($profil);
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

    // ── Rôles personnalisés (RBAC niveau 3) ──

    /**
     * Tous les rôles (système + personnalisés) pour l'administration.
     *
     * @return array<int,array{code:string,libelle:string,systeme:bool}>
     */
    public function roles(): array
    {
        $systeme = array_map(fn (string $code) => [
            'code' => $code,
            'libelle' => Permissions::LIBELLES[$code] ?? $code,
            'systeme' => true,
        ], Permissions::PROFILS);

        $personnalises = $this->rolesPersonnalisesModeles()
            ->map(fn (Role $r) => [
                'code' => $r->code,
                'libelle' => $r->libelle,
                'systeme' => false,
            ])
            ->all();

        return array_merge($systeme, $personnalises);
    }

    /**
     * Codes de rôle valides (système + personnalisés), SUPER_ADMIN inclus.
     * Sert à la validation du rôle d'un utilisateur.
     *
     * @return string[]
     */
    public function codes(): array
    {
        return array_values(array_unique(array_merge(
            Permissions::PROFILS,
            $this->codesPersonnalises(),
        )));
    }

    public function estSysteme(string $code): bool
    {
        return in_array($code, Permissions::PROFILS, true);
    }

    public function libelle(string $code): string
    {
        if ($this->estSysteme($code)) {
            return Permissions::LIBELLES[$code] ?? $code;
        }

        return optional($this->rolesPersonnalisesModeles()->firstWhere('code', $code))->libelle ?? $code;
    }

    /**
     * Crée un rôle personnalisé (permissions initiales optionnelles).
     * Validation (format du code, unicité, non-système) faite en amont.
     *
     * @param  string[]  $permissions
     */
    public function creerRole(string $code, string $libelle, array $permissions = []): Role
    {
        $role = Role::create(['code' => $code, 'libelle' => $libelle, 'systeme' => false]);

        if ($permissions !== []) {
            $this->remplacer($code, $permissions); // vide déjà le cache
        } else {
            $this->vider();
        }

        return $role;
    }

    public function renommerRole(string $code, string $libelle): Role
    {
        $role = Role::where('code', $code)->firstOrFail();
        $role->update(['libelle' => $libelle]);
        $this->vider();

        return $role;
    }

    /** Supprime un rôle personnalisé et ses permissions. */
    public function supprimerRole(string $code): void
    {
        DB::transaction(function () use ($code) {
            RolePermission::where('role', $code)->delete();
            Role::where('code', $code)->delete();
        });

        $this->vider();
    }

    public function roleExiste(string $code): bool
    {
        return $this->estSysteme($code) || in_array($code, $this->codesPersonnalises(), true);
    }

    /**
     * @return Collection<int,Role>
     */
    private function rolesPersonnalisesModeles()
    {
        if (! Schema::hasTable('roles')) {
            return collect();
        }

        return Role::query()->where('systeme', false)->orderBy('libelle')->get();
    }

    /**
     * @return string[]
     */
    private function codesPersonnalises(): array
    {
        return $this->rolesPersonnalisesModeles()->pluck('code')->all();
    }

    /**
     * @return array<string,string[]>
     */
    private function construire(): array
    {
        $overrides = $this->overrides();
        $matrice = [];

        // Tous les rôles éditables : profils système (hors SUPER_ADMIN) + personnalisés.
        foreach ($this->codes() as $code) {
            if ($code === Permissions::SUPER_ADMIN) {
                continue;
            }
            $matrice[$code] = $overrides[$code] ?? $this->defaut($code);
        }

        return $matrice;
    }

    /**
     * Défaut immuable d'un rôle : config/profils.php pour un profil système,
     * repli sur la constante ; un rôle personnalisé n'a pas de défaut (= aucune
     * permission tant qu'on ne lui en attribue pas).
     *
     * @return string[]
     */
    private function defaut(string $profil): array
    {
        if (! $this->estSysteme($profil)) {
            return [];
        }

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
