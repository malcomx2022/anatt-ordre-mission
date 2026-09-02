<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rôle personnalisé (RBAC niveau 3).
 *
 * Les profils système (App\Support\Permissions::PROFILS) ne sont PAS stockés ici :
 * cette table ne contient que les rôles créés à la demande. Cf. PermissionRepository.
 */
class Role extends Model
{
    protected $fillable = ['code', 'libelle', 'systeme'];

    protected function casts(): array
    {
        return ['systeme' => 'boolean'];
    }
}
