<?php

namespace App\Http\Middleware;

use App\Models\OrdreMission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrdreOwnership
{
    /**
     * Un AGENT ne peut acceder qu'a ses propres ordres de mission.
     * Les autres roles (CHEF_SERVICE, DRH, DIRECTEUR, ADMIN) ont acces a tous.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifie.'], 401);
        }

        // Roles privilegies: acces a tous les OM
        if ($user->hasRole('CHEF_SERVICE', 'DRH', 'DIRECTEUR', 'ADMIN')) {
            return $next($request);
        }

        // AGENT: verifier ownership
        $ordre = $request->route('ordre');

        if ($ordre instanceof OrdreMission && $user->agent) {
            if ($ordre->agent_id !== $user->agent->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez acceder qu\'a vos propres ordres de mission.',
                ], 403);
            }
        }

        return $next($request);
    }
}
