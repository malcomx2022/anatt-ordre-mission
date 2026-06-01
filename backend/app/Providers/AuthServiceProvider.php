<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Le SUPER_ADMIN a accès implicite à TOUT.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Une Gate par clé de permission, adossée à la matrice des profils.
        foreach (Permissions::TOUTES as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
