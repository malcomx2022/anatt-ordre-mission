<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        Log::warning('Acces refuse par middleware CheckRole', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'roles_requis' => $roles,
            'route' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Acces refuse. Role requis : ' . implode(' ou ', $roles),
        ], 403);
    }
}
