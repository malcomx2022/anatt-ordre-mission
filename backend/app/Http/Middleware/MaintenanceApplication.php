<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Parametres;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paramètres › Maintenance « Mode maintenance » : l'API répond 503 à tous,
 * sauf au super administrateur, qui garde la main pour lever le mode.
 *
 * La connexion reste ouverte (sinon le super administrateur ne pourrait plus
 * se connecter pour désactiver le mode). Le SPA bascule sur son écran
 * Maintenance au premier 503 et y revient seul une fois le mode levé.
 * Distinct de `php artisan down`, réservé aux mises à jour (DEPLOY-CPANEL.md).
 */
class MaintenanceApplication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Parametres::maintenance() || $request->is('api/auth/login', 'api/auth/logout')) {
            return $next($request);
        }

        $utilisateur = $request->user('sanctum');
        if ($utilisateur instanceof User && $utilisateur->role === User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        return response()->json(['message' => 'Application en maintenance. Merci de réessayer plus tard.'], 503)
            ->header('Retry-After', '300');
    }
}
