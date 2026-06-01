<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BaremeController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\RapportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - ANaTT Gestion des Missions
|--------------------------------------------------------------------------
| Autorisation par PERMISSION (middleware `permission:`), adossée aux Gates définies
| dans AuthServiceProvider. Le SUPER_ADMIN passe toute garde (Gate::before).
*/

// ── Authentification (publiques + rate limiting strict) ──
Route::prefix('auth')->middleware('throttle:login')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('mot-de-passe/email', [AuthController::class, 'sendResetLink']);
    Route::post('mot-de-passe/reset', [AuthController::class, 'resetPassword']);
});

// ── Routes protégées par Sanctum + rate limiting global ──
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth (tous les utilisateurs connectés)
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    // Permissions de l'utilisateur courant (pilote l'UI du frontend).
    Route::get('auth/me/permissions', [AuthController::class, 'permissions']);

    // Agents (référentiel personnel)
    Route::middleware('permission:personnel.consulter')->group(function () {
        Route::get('agents', [AgentController::class, 'index']);
        Route::get('agents/recherche', [AgentController::class, 'recherche']);
        Route::get('agents/{agent}', [AgentController::class, 'show']);
    });
    Route::middleware('permission:personnel.gerer')->group(function () {
        Route::post('agents', [AgentController::class, 'store']);
        Route::put('agents/{agent}', [AgentController::class, 'update']);
        Route::delete('agents/{agent}', [AgentController::class, 'destroy']);
        Route::post('agents/import', [AgentController::class, 'import']);
    });

    // Ordres de mission - consultation
    Route::middleware('permission:om.consulter')->group(function () {
        Route::get('ordres', [MissionController::class, 'index']);
        Route::get('ordres/{ordre}', [MissionController::class, 'show']);
        Route::get('ordres/{ordre}/pdf', [MissionController::class, 'pdf']);
        Route::get('ordres/{ordre}/pdf/download', [MissionController::class, 'pdfDownload']);
        Route::get('ordres/{ordre}/historique', [MissionController::class, 'historique']);
    });

    // Missions (retranscription SEMO : 1 mission + N OM)
    Route::post('missions', [MissionController::class, 'storeMission'])->middleware('permission:om.retranscrire');

    // Validation / rejet des OM
    Route::middleware('permission:om.valider')->group(function () {
        Route::post('ordres/{ordre}/valider', [ValidationController::class, 'valider']);
        Route::post('ordres/{ordre}/rejeter', [ValidationController::class, 'rejeter']);
    });

    // Paiements (mode GÉNÉRATION)
    Route::middleware('permission:paiements.consulter')->group(function () {
        Route::get('paiements/lots', [PaiementController::class, 'index']);
        Route::get('paiements/lots/previsualiser', [PaiementController::class, 'previsualiser']);
        Route::get('paiements/statuts', [PaiementController::class, 'statuts']);
        Route::get('paiements/historique', [PaiementController::class, 'historique']);
        Route::get('paiements/lots/{lot}/download', [PaiementController::class, 'download']);
        Route::get('paiements/lots/job/{trackingId}', [PaiementController::class, 'statutJob']);
    });
    Route::middleware('throttle:paiement')->group(function () {
        Route::post('paiements/lots/generer', [PaiementController::class, 'generer'])->middleware('permission:paiements.generer');
        Route::post('paiements/lots/{lot}/erreur', [PaiementController::class, 'marquerErreur'])->middleware('permission:paiements.generer');
        Route::post('paiements/lots/{lot}/confirmer', [PaiementController::class, 'confirmer'])->middleware('permission:paiements.confirmer');
    });

    // Barème (per diem)
    Route::middleware('permission:baremes.consulter')->group(function () {
        Route::get('baremes', [BaremeController::class, 'index']);
        Route::get('baremes/{bareme}', [BaremeController::class, 'show']);
        Route::get('taux', [BaremeController::class, 'index']); // alias rétro-compatible
    });
    Route::middleware('permission:baremes.modifier')->group(function () {
        Route::post('baremes', [BaremeController::class, 'store']);
        Route::put('baremes/{bareme}', [BaremeController::class, 'update']);
        Route::delete('baremes/{bareme}', [BaremeController::class, 'destroy']);
    });

    // États & Rapports
    Route::middleware('permission:rapports.consulter')->group(function () {
        Route::get('rapports/mensuel', [RapportController::class, 'mensuel']);
        Route::get('rapports/agents', [RapportController::class, 'agents']);
        Route::get('rapports/operateurs', [RapportController::class, 'operateurs']);
        Route::get('rapports/zones', [RapportController::class, 'zones']);
        Route::get('rapports/imputations', [RapportController::class, 'imputations']);
        Route::get('rapports/export', [RapportController::class, 'export'])->middleware('throttle:export');
        Route::get('rapports/export/download', [RapportController::class, 'exportDownload']);
        Route::post('rapports/export-async', [RapportController::class, 'exportAsync'])->middleware('throttle:export');
        Route::get('rapports/export/job/{trackingId}', [RapportController::class, 'statutExport']);
    });

    // Journal d'audit
    Route::middleware('permission:audit.consulter')->group(function () {
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/{audit}', [AuditController::class, 'show']);
    });

    // Administration
    Route::prefix('admin')->group(function () {
        // Gestion des utilisateurs (les actions sur les SUPER_ADMIN exigent superadmins.gerer, géré dans le contrôleur).
        Route::middleware('permission:utilisateurs.gerer')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);
            Route::post('users/{user}/statut', [UserController::class, 'setActif']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        });

        // Paramètres & sauvegardes : SUPER_ADMIN uniquement (parametres.gerer).
        Route::middleware('permission:parametres.gerer')->group(function () {
            Route::get('settings', function () {
                $path = storage_path('app/settings.json');
                $settings = file_exists($path) ? json_decode(file_get_contents($path), true) : [];

                return response()->json(['data' => $settings]);
            });
            Route::put('settings', function (\Illuminate\Http\Request $request) {
                file_put_contents(storage_path('app/settings.json'), json_encode($request->all(), JSON_PRETTY_PRINT));

                return response()->json(['data' => $request->all(), 'message' => 'Parametres enregistres.']);
            });

            Route::get('backups', function () {
                $dir = storage_path('app/backups');
                if (! is_dir($dir)) {
                    return response()->json(['data' => []]);
                }
                $files = collect(scandir($dir))->filter(fn ($f) => str_ends_with($f, '.sql') || str_ends_with($f, '.sql.gz'))
                    ->values()->map(function ($f) use ($dir) {
                        $path = "{$dir}/{$f}";
                        $size = filesize($path);

                        return [
                            'name' => $f,
                            'date' => date('d/m/Y H:i', filemtime($path)),
                            'size' => $size < 1048576 ? round($size / 1024, 1).' KB' : round($size / 1048576, 1).' MB',
                            'type' => str_contains($f, '_020000') ? 'Automatique' : 'Manuel',
                            'status' => 'Complet',
                        ];
                    })->sortByDesc('date')->values();

                return response()->json(['data' => $files]);
            });
            Route::post('backups', function () {
                \Illuminate\Support\Facades\Artisan::call('db:backup', ['--compress' => true]);

                return response()->json(['message' => 'Sauvegarde creee.']);
            });
            Route::delete('backups/{name}', function (string $name) {
                $path = storage_path('app/backups/'.basename($name));
                if (file_exists($path)) {
                    unlink($path);
                }

                return response()->json(['message' => 'Sauvegarde supprimee.']);
            });
        });
    });
});
