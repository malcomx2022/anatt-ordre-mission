<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BaremeController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\RapportController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - ANaTT Gestion des Missions
|--------------------------------------------------------------------------
*/

// ── Authentification (publiques + rate limiting strict) ──
Route::prefix('auth')->middleware('throttle:login')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('mot-de-passe/email', [AuthController::class, 'sendResetLink']);
    Route::post('mot-de-passe/reset', [AuthController::class, 'resetPassword']);
});

// ── Routes protégées par Sanctum + rate limiting global ──
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth (tous les utilisateurs connectes)
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Agents - lecture pour tous, ecriture pour gestionnaire_om (admin bypass)
    Route::get('agents', [AgentController::class, 'index']);
    Route::get('agents/recherche', [AgentController::class, 'recherche']); // autocomplétion wizard OM
    Route::get('agents/{agent}', [AgentController::class, 'show']);
    Route::middleware('role:gestionnaire_om')->group(function () {
        Route::post('agents', [AgentController::class, 'store']);
        Route::put('agents/{agent}', [AgentController::class, 'update']);
        Route::delete('agents/{agent}', [AgentController::class, 'destroy']);
        Route::post('agents/import', [AgentController::class, 'import']);
    });

    // Ordres de Mission - lecture pour tous ; écriture via missions (cf. 3.3)
    Route::get('ordres', [MissionController::class, 'index']);
    Route::get('ordres/{ordre}', [MissionController::class, 'show']);
    Route::get('ordres/{ordre}/pdf', [MissionController::class, 'pdf']);
    Route::get('ordres/{ordre}/pdf/download', [MissionController::class, 'pdfDownload']);
    Route::get('ordres/{ordre}/historique', [MissionController::class, 'historique']);

    // Missions (retranscription SEMO : 1 mission + N OM) - gestionnaire_om
    Route::middleware('role:gestionnaire_om')->group(function () {
        Route::post('missions', [MissionController::class, 'storeMission']);
    });

    // Validation workflow OM (retranscription) - gestionnaire_om (admin bypass)
    Route::middleware('role:gestionnaire_om')->group(function () {
        Route::post('ordres/{ordre}/valider', [ValidationController::class, 'valider']);
        Route::post('ordres/{ordre}/rejeter', [ValidationController::class, 'rejeter']);
    });

    // Paiements (mode GÉNÉRATION) - lecture pour tous, actions pour agent_financier
    Route::get('paiements/lots', [PaiementController::class, 'index']);
    Route::get('paiements/lots/previsualiser', [PaiementController::class, 'previsualiser']);
    Route::get('paiements/statuts', [PaiementController::class, 'statuts']);
    Route::get('paiements/historique', [PaiementController::class, 'historique']);
    Route::middleware('role:agent_financier')->group(function () {
        Route::get('paiements/lots/{lot}/download', [PaiementController::class, 'download']);
        Route::get('paiements/lots/job/{trackingId}', [PaiementController::class, 'statutJob']);
        Route::middleware('throttle:paiement')->group(function () {
            Route::post('paiements/lots/generer', [PaiementController::class, 'generer']);
            Route::post('paiements/lots/{lot}/confirmer', [PaiementController::class, 'confirmer']);
            Route::post('paiements/lots/{lot}/erreur', [PaiementController::class, 'marquerErreur']);
        });
    });

    // Barème (per diem) - lecture pour tous, modification pour admin
    Route::get('baremes', [BaremeController::class, 'index']);
    Route::get('baremes/{bareme}', [BaremeController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('baremes', [BaremeController::class, 'store']);
        Route::put('baremes/{bareme}', [BaremeController::class, 'update']);
        Route::delete('baremes/{bareme}', [BaremeController::class, 'destroy']);
    });
    // Alias rétro-compatibles (ancien chemin /taux) -> lecture du barème
    Route::get('taux', [BaremeController::class, 'index']);

    // États & Rapports - tout utilisateur authentifié (lecteur inclus)
    Route::get('rapports/mensuel', [RapportController::class, 'mensuel']);
    Route::get('rapports/agents', [RapportController::class, 'agents']);
    Route::get('rapports/operateurs', [RapportController::class, 'operateurs']);
    Route::get('rapports/zones', [RapportController::class, 'zones']);
    Route::get('rapports/imputations', [RapportController::class, 'imputations']);
    Route::get('rapports/export', [RapportController::class, 'export'])->middleware('throttle:export');
    Route::get('rapports/export/download', [RapportController::class, 'exportDownload']);
    Route::post('rapports/export-async', [RapportController::class, 'exportAsync'])->middleware('throttle:export');
    Route::get('rapports/export/job/{trackingId}', [RapportController::class, 'statutExport']);

    // Admin - Gestion utilisateurs
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('users', function (\Illuminate\Http\Request $request) {
            $query = \App\Models\User::query();
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            }
            if ($role = $request->get('role')) {
                $query->where('role', $role);
            }
            return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 15)));
        });
        Route::post('users', function (\Illuminate\Http\Request $request) {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'role' => 'required|in:admin,gestionnaire_om,agent_financier,lecteur',
            ]);

            $user = \App\Models\User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ]);
            $user->assignRole($data['role']);

            return response()->json([
                'data' => $user->fresh(),
                'message' => 'Utilisateur cree avec succes.',
            ], 201);
        });
        Route::delete('users/{user}', function (\App\Models\User $user) {
            $user->delete();
            return response()->json(['message' => 'Utilisateur supprime.']);
        });

        // Settings (stockage fichier JSON)
        Route::get('settings', function () {
            $path = storage_path('app/settings.json');
            $settings = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
            return response()->json(['data' => $settings]);
        });
        Route::put('settings', function (\Illuminate\Http\Request $request) {
            $path = storage_path('app/settings.json');
            file_put_contents($path, json_encode($request->all(), JSON_PRETTY_PRINT));
            return response()->json(['data' => $request->all(), 'message' => 'Parametres enregistres.']);
        });

        // Backups
        Route::get('backups', function () {
            $dir = storage_path('app/backups');
            if (! is_dir($dir)) return response()->json(['data' => []]);
            $files = collect(scandir($dir))->filter(fn ($f) => str_ends_with($f, '.sql') || str_ends_with($f, '.sql.gz'))
                ->values()->map(function ($f) use ($dir) {
                    $path = "{$dir}/{$f}";
                    $size = filesize($path);
                    return [
                        'name' => $f,
                        'date' => date('d/m/Y H:i', filemtime($path)),
                        'size' => $size < 1048576 ? round($size / 1024, 1) . ' KB' : round($size / 1048576, 1) . ' MB',
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
            $name = basename($name);
            $path = storage_path("app/backups/{$name}");
            if (file_exists($path)) { unlink($path); }
            return response()->json(['message' => 'Sauvegarde supprimee.']);
        });
    });

    // Audit - admin uniquement
    Route::middleware('role:admin')->group(function () {
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/{audit}', [AuditController::class, 'show']);
    });
});
