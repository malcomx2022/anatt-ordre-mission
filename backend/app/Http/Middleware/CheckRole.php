<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifie.'], 401);
        }

        // ADMIN a toujours acces
        if ($user->role === 'ADMIN') {
            return $next($request);
        }

        if (empty($roles) || in_array($user->role, $roles)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Acces refuse. Role requis : ' . implode(' ou ', $roles),
        ], 403);
    }
}
