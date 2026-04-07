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

// ── Authentification (publiques) ──
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('mot-de-passe/email', [AuthController::class, 'sendResetLink']);
    Route::post('mot-de-passe/reset', [AuthController::class, 'resetPassword']);
});

// ── Routes protégées par Sanctum ──
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Agents
    Route::apiResource('agents', AgentController::class);
    Route::post('agents/import', [AgentController::class, 'import']);

    // Ordres de Mission
    Route::apiResource('ordres', MissionController::class);
    Route::get('ordres/{ordre}/pdf', [MissionController::class, 'pdf']);
    Route::get('ordres/{ordre}/pdf/download', [MissionController::class, 'pdfDownload']);
    Route::get('ordres/{ordre}/historique', [MissionController::class, 'historique']);

    // Validation workflow
    Route::post('ordres/{ordre}/soumettre', [ValidationController::class, 'soumettre']);
    Route::post('ordres/{ordre}/valider-n1', [ValidationController::class, 'validerN1']);
    Route::post('ordres/{ordre}/approuver', [ValidationController::class, 'approuver']);
    Route::post('ordres/{ordre}/rejeter', [ValidationController::class, 'rejeter']);

    // Paiements
    Route::get('paiements', [PaiementController::class, 'index']);
    Route::post('paiements/generer/moov', [PaiementController::class, 'genererMoov']);
    Route::post('paiements/generer/mtn', [PaiementController::class, 'genererMtn']);
    Route::get('paiements/statuts', [PaiementController::class, 'statuts']);
    Route::post('paiements/reconcilier', [PaiementController::class, 'reconcilier']);
    Route::get('paiements/historique', [PaiementController::class, 'historique']);

    // Taux Per Diem
    Route::get('taux', [TauxController::class, 'index']);
    Route::post('taux', [TauxController::class, 'store']);
    Route::put('taux/{taux}', [TauxController::class, 'update']);

    // Rapports
    Route::get('rapports/mensuel', [RapportController::class, 'mensuel']);
    Route::get('rapports/agents', [RapportController::class, 'agents']);
    Route::get('rapports/export', [RapportController::class, 'export']);

    // Audit
    Route::get('audit', [AuditController::class, 'index']);
    Route::get('audit/{audit}', [AuditController::class, 'show']);
});
