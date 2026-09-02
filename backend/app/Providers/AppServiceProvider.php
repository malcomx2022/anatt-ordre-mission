<?php

namespace App\Providers;

use App\Support\PermissionRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Schema::defaultStringLength(191);

        // Matrice RBAC éditable : une seule instance (cache mémoire + Redis) par cycle.
        $this->app->singleton(PermissionRepository::class);

        // Telescope (require-dev) : chargé uniquement en local ET s'il est installé.
        // Évite un crash en production où le paquet est absent (composer --no-dev).
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // Politique de mot de passe par défaut (application financière) :
        // ≥ 12 caractères, majuscules + minuscules, chiffres et symboles.
        // Utilisée partout via Password::defaults() (création/MAJ/réinitialisation).
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        // Rate limiting global API: 60 req/min par utilisateur
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting strict pour login : 5 tentatives/min, verrou combiné email + IP.
        // Le verrou par identifiant bloque le credential stuffing ciblé multi-IP ;
        // le verrou par IP bloque le balayage d'emails depuis une même source.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login|'.$request->ip()),
                Limit::perMinute(5)->by('login|'.$email.'|'.$request->ip()),
            ];
        });

        // Rate limiting pour les exports: 10/min (operations lourdes)
        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting pour generation paiements: 5/min
        RateLimiter::for('paiement', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
