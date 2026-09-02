<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne = un override de permission pour un utilisateur.
 *   granted = true  → permission accordée EN PLUS du profil ;
 *   granted = false → permission RETIRÉE de ce que le profil donnerait.
 *
 * Cf. App\Services\UserPermissionService et User::permissions().
 */
class UserPermission extends Model
{
    protected $fillable = ['user_id', 'permission', 'granted'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
