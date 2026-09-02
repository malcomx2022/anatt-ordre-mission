<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une ligne = une permission attribuée à un profil (matrice éditable).
 *
 * Cf. App\Support\PermissionRepository : cette table prime sur config/profils.php
 * pour les profils personnalisés ; les autres retombent sur la config.
 */
class RolePermission extends Model
{
    protected $fillable = ['role', 'permission'];
}
