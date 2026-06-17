<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Tant qu'un compte doit changer son mot de passe (création ou réinitialisation
     * administrative), on bloque tout l'API sauf l'échappatoire indispensable :
     * se déconnecter, consulter son profil/permissions et changer son mot de passe.
     * Empêche qu'un mot de passe temporaire (connu d'un tiers) serve de credential durable.
     */
    private const ROUTES_AUTORISEES = [
        'api/auth/logout',
        'api/auth/me',
        'api/auth/me/permissions',
        'api/auth/mot-de-passe/changer',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! in_array($request->path(), self::ROUTES_AUTORISEES, true)) {
            return response()->json([
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 423);
        }

        return $next($request);
    }
}
