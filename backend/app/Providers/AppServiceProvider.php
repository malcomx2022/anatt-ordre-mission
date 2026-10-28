<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Parametres;
use App\Support\PermissionRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
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

        // Lien de réinitialisation : pointe vers la SPA (route /reinitialiser/{token})
        // et embarque l'email en query — le formulaire de reset les renvoie à l'API.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $base = rtrim((string) config('app.frontend_url'), '/');

            return $base.'/reinitialiser/'.$token.'?email='.urlencode($notifiable->getEmailForPasswordReset());
        });

        // Durée de session (Paramètres › Sécurité) : quand elle est réglée, elle remplace
        // SANCTUM_EXPIRATION — un jeton expire N minutes après la connexion, que la durée
        // soit plus courte ou plus longue. Non réglée : la validité calculée par Sanctum.
        Sanctum::authenticateAccessTokensUsing(function ($jeton, bool $valide): bool {
            $duree = Parametres::dureeSession();
            if ($duree === null) {
                return $valide;
            }

            return $jeton->tokenable instanceof User
                && (! $jeton->expires_at || ! $jeton->expires_at->isPast())
                && $jeton->created_at->gt(now()->subMinutes($duree));
        });

        // Rate limiting global API: 60 req/min par utilisateur
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting strict pour login : 5 tentatives/min par défaut (Paramètres ›
        // Sécurité, 3 à 20), verrou combiné email + IP.
        // Le verrou par identifiant bloque le credential stuffing ciblé multi-IP ;
        // le verrou par IP bloque le balayage d'emails depuis une même source.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(Parametres::tentativesConnexion())->by('login|'.$request->ip()),
                Limit::perMinute(Parametres::tentativesConnexion())->by('login|'.$email.'|'.$request->ip()),
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
