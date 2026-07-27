<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Profils RBAC (cf. App\Support\Permissions). */
    public const ROLE_SUPER_ADMIN = Permissions::SUPER_ADMIN;
    public const ROLE_ADMIN = Permissions::ADMIN;
    public const ROLE_GESTIONNAIRE_OM = Permissions::GESTIONNAIRE_OM;
    public const ROLE_AGENT_FINANCIER = Permissions::AGENT_FINANCIER;
    /** 1re signature du circuit de validation (« Vu » du DAF). */
    public const ROLE_DAF = Permissions::DAF;
    /** 2e et dernière signature : le DG arrête l'état, l'OM devient payable. */
    public const ROLE_DG = Permissions::DG;
    public const ROLE_CONTROLEUR = Permissions::CONTROLEUR;
    public const ROLE_LECTEUR = Permissions::LECTEUR;

    public const ROLES = Permissions::PROFILS;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'cree_par',
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
            'actif' => 'boolean',
            'must_change_password' => 'boolean',
            'derniere_connexion' => 'datetime',
        ];
    }

    /** Compte ayant créé cet utilisateur (auteur). */
    public function creePar()
    {
        return $this->belongsTo(self::class, 'cree_par');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Permissions effectives du profil (SUPER_ADMIN -> toutes). */
    public function permissions(): array
    {
        return Permissions::pour((string) $this->role);
    }

    /** Le SUPER_ADMIN a toujours la permission (cohérent avec Gate::before). */
    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || in_array($permission, $this->permissions(), true);
    }
}
