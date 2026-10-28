<?php

namespace App\Http\Middleware;

use App\Support\Parametres;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paramètres › Sécurité « Forcer HTTPS » : l'API refuse le HTTP en clair.
 *
 * Une lecture (GET/HEAD) est redirigée vers la même adresse en HTTPS ; une
 * écriture est refusée (403) plutôt que redirigée, car une redirection ferait
 * perdre son corps à la requête — et l'aurait déjà transmis en clair.
 * À n'activer qu'une fois le certificat en place : sur cPanel, AutoSSL.
 */
class ForcerHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Parametres::forcerHttps() || $request->isSecure()) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return response()->json(['message' => 'HTTPS requis : cette requête doit être envoyée en https://.'], 403);
    }
}
