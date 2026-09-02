<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPermission;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Overrides de permissions par utilisateur (RBAC flexible — niveau 2).
 *
 * Combine la base du profil avec les accords/retraits propres à l'utilisateur.
 * Le SUPER_ADMIN est hors périmètre (accès implicite total).
 */
class UserPermissionService
{
    /**
     * Accords et retraits d'un utilisateur, groupés.
     *
     * @return array{granted: string[], revoked: string[]}
     */
    public function overrides(User $user): array
    {
        $lignes = $user->relationLoaded('permissionOverrides')
            ? $user->permissionOverrides
            : $user->permissionOverrides()->get();

        return [
            'granted' => $lignes->where('granted', true)->pluck('permission')->values()->all(),
            'revoked' => $lignes->where('granted', false)->pluck('permission')->values()->all(),
        ];
    }

    /**
     * Permissions effectives d'un utilisateur (base ∪ accordées − retirées).
     *
     * @return string[]
     */
    public function effectives(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Permissions::TOUTES;
        }

        $base = Permissions::pour((string) $user->role);
        $overrides = $this->overrides($user);

        return self::combiner($base, $overrides['granted'], $overrides['revoked']);
    }

    /**
     * Remplace intégralement les overrides d'un utilisateur et renvoie l'effectif.
     * La validation métier est faite en amont (contrôleur).
     *
     * @param  string[]  $granted
     * @param  string[]  $revoked
     * @return string[]
     */
    public function remplacer(User $user, array $granted, array $revoked): array
    {
        $granted = array_values(array_unique($granted));
        $revoked = array_values(array_unique(array_diff($revoked, $granted)));

        DB::transaction(function () use ($user, $granted, $revoked) {
            UserPermission::where('user_id', $user->id)->delete();

            $lignes = [];
            foreach ($granted as $p) {
                $lignes[] = ['user_id' => $user->id, 'permission' => $p, 'granted' => true, 'created_at' => now(), 'updated_at' => now()];
            }
            foreach ($revoked as $p) {
                $lignes[] = ['user_id' => $user->id, 'permission' => $p, 'granted' => false, 'created_at' => now(), 'updated_at' => now()];
            }

            if ($lignes !== []) {
                UserPermission::insert($lignes);
            }
        });

        $user->unsetRelation('permissionOverrides');
        $user->oublierPermissions();

        return $this->effectives($user);
    }

    /**
     * base ∪ accordées − retirées, dédupliqué.
     *
     * @param  string[]  $base
     * @param  string[]  $granted
     * @param  string[]  $revoked
     * @return string[]
     */
    public static function combiner(array $base, array $granted, array $revoked): array
    {
        return array_values(array_unique(array_diff(array_merge($base, $granted), $revoked)));
    }
}
