<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\RapportController;
use App\Http\Controllers\Api\TauxController;
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

    // Agents - lecture pour tous, ecriture pour DRH
    Route::get('agents', [AgentController::class, 'index']);
    Route::get('agents/{agent}', [AgentController::class, 'show']);
    Route::middleware('role:DRH')->group(function () {
        Route::post('agents', [AgentController::class, 'store']);
        Route::put('agents/{agent}', [AgentController::class, 'update']);
        Route::delete('agents/{agent}', [AgentController::class, 'destroy']);
        Route::post('agents/import', [AgentController::class, 'import']);
    });

    // Ordres de Mission - CRUD avec controle ownership AGENT
    Route::apiResource('ordres', MissionController::class)->middleware('ordre.owner');
    Route::middleware('ordre.owner')->group(function () {
        Route::get('ordres/{ordre}/pdf', [MissionController::class, 'pdf']);
        Route::get('ordres/{ordre}/pdf/download', [MissionController::class, 'pdfDownload']);
        Route::get('ordres/{ordre}/historique', [MissionController::class, 'historique']);
    });

    // Validation workflow - soumission avec ownership, validation par roles specifiques
    Route::post('ordres/{ordre}/soumettre', [ValidationController::class, 'soumettre'])
        ->middleware('ordre.owner');
    Route::post('ordres/{ordre}/valider-n1', [ValidationController::class, 'validerN1'])
        ->middleware('role:CHEF_SERVICE');
    Route::post('ordres/{ordre}/approuver', [ValidationController::class, 'approuver'])
        ->middleware('role:DIRECTEUR');
    Route::post('ordres/{ordre}/rejeter', [ValidationController::class, 'rejeter'])
        ->middleware('role:CHEF_SERVICE,DIRECTEUR');

    // Paiements - lecture restreinte, generation pour DRH
    Route::get('paiements', [PaiementController::class, 'index']);
    Route::middleware('role:DRH,CHEF_SERVICE,DIRECTEUR')->group(function () {
        Route::get('paiements/statuts', [PaiementController::class, 'statuts']);
        Route::get('paiements/historique', [PaiementController::class, 'historique']);
    });
    Route::middleware(['role:DRH', 'throttle:paiement'])->group(function () {
        Route::post('paiements/generer/moov', [PaiementController::class, 'genererMoov']);
        Route::post('paiements/generer/mtn', [PaiementController::class, 'genererMtn']);
        Route::post('paiements/reconcilier', [PaiementController::class, 'reconcilier']);
    });

    // Taux Per Diem - lecture pour tous, modification pour DRH
    Route::get('taux', [TauxController::class, 'index']);
    Route::middleware('role:DRH')->group(function () {
        Route::post('taux', [TauxController::class, 'store']);
        Route::put('taux/{taux}', [TauxController::class, 'update']);
    });

    // Rapports - CHEF_SERVICE, DRH, DIRECTEUR
    Route::middleware('role:CHEF_SERVICE,DRH,DIRECTEUR')->group(function () {
        Route::get('rapports/mensuel', [RapportController::class, 'mensuel']);
        Route::get('rapports/agents', [RapportController::class, 'agents']);
        Route::get('rapports/export', [RapportController::class, 'export'])->middleware('throttle:export');
        Route::get('rapports/export/download', [RapportController::class, 'exportDownload']);
    });

    // Admin - Gestion utilisateurs
    Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
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
                'role' => 'required|in:AGENT,CHEF_SERVICE,DRH,DIRECTEUR,ADMIN',
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
    });

    // Audit - DIRECTEUR uniquement
    Route::middleware('role:DIRECTEUR')->group(function () {
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/{audit}', [AuditController::class, 'show']);
    });
});
