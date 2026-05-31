<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Rôles RBAC de l'application. */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_GESTIONNAIRE_OM = 'gestionnaire_om';
    public const ROLE_AGENT_FINANCIER = 'agent_financier';
    public const ROLE_LECTEUR = 'lecteur';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GESTIONNAIRE_OM,
        self::ROLE_AGENT_FINANCIER,
        self::ROLE_LECTEUR,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Le role ne peut etre modifie que via assignRole() pour eviter
     * qu'un utilisateur s'auto-promeuve via mass assignment.
     */
    public function assignRole(string $role): self
    {
        $this->role = $role;
        $this->save();
        return $this;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
