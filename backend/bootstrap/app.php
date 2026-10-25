<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Autorisation par clé de permission (adossée aux Gates) : permission:{cle}
            'permission' => Authorize::class,
        ]);

        // Pas de page de login côté API (SPA séparée) : aucune redirection d'un
        // invité sur /api/*, l'exception est rendue en 401 JSON plus bas.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');

        // Headers de securite pour les reponses API
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toute route /api/* répond en JSON, même sans en-tête Accept (requête
        // ouverte dans un navigateur, outil tiers) : sinon Laravel renvoie une
        // page HTML (405, 404…) ou tente une redirection.
        $enJson = fn (Request $request): bool => $request->expectsJson() || $request->is('api/*');

        $exceptions->renderable(function (ValidationException $e, $request) use ($enJson) {
            if ($enJson($request)) {
                return response()->json([
                    'message' => 'Donnees invalides.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Sans ce rendu, Laravel tenterait de rediriger vers une route « login »
        // inexistante (500).
        $exceptions->renderable(function (AuthenticationException $e, $request) use ($enJson) {
            if ($enJson($request)) {
                return response()->json([
                    'message' => 'Non authentifie.',
                ], 401);
            }
        });

        $exceptions->renderable(function (ModelNotFoundException $e, $request) use ($enJson) {
            if ($enJson($request)) {
                return response()->json([
                    'message' => 'Ressource introuvable.',
                ], 404);
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, $request) use ($enJson) {
            if ($enJson($request)) {
                return response()->json([
                    'message' => 'Route introuvable.',
                ], 404);
            }
        });

        $exceptions->renderable(function (AccessDeniedHttpException $e, $request) use ($enJson) {
            if ($enJson($request)) {
                return response()->json([
                    'message' => 'Acces refuse.',
                ], 403);
            }
        });

        // Toute HttpException volontaire (abort(403), abort(422), abort(405)…) doit
        // conserver SON code de statut et son message, et non être avalée par le
        // catch-all \Throwable ci-dessous (qui renverrait un 500 trompeur).
        $exceptions->renderable(function (HttpExceptionInterface $e, $request) use ($enJson) {
            if ($enJson($request)) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Requete non aboutie.',
                ], $e->getStatusCode());
            }
        });

        $exceptions->renderable(function (Throwable $e, $request) use ($enJson) {
            if ($enJson($request) && ! app()->hasDebugModeEnabled()) {
                return response()->json([
                    'message' => 'Erreur interne du serveur.',
                ], 500);
            }
        });
    })->create();
