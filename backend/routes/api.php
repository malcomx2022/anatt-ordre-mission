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

    // Ordres de Mission - CRUD accessible a tous les connectes
    Route::apiResource('ordres', MissionController::class);
    Route::get('ordres/{ordre}/pdf', [MissionController::class, 'pdf']);
    Route::get('ordres/{ordre}/pdf/download', [MissionController::class, 'pdfDownload']);
    Route::get('ordres/{ordre}/historique', [MissionController::class, 'historique']);

    // Validation workflow - soumission par tous, validation par roles specifiques
    Route::post('ordres/{ordre}/soumettre', [ValidationController::class, 'soumettre']);
    Route::post('ordres/{ordre}/valider-n1', [ValidationController::class, 'validerN1'])
        ->middleware('role:CHEF_SERVICE');
    Route::post('ordres/{ordre}/approuver', [ValidationController::class, 'approuver'])
        ->middleware('role:DIRECTEUR');
    Route::post('ordres/{ordre}/rejeter', [ValidationController::class, 'rejeter'])
        ->middleware('role:CHEF_SERVICE,DIRECTEUR');

    // Paiements - lecture pour tous, generation pour DRH
    Route::get('paiements', [PaiementController::class, 'index']);
    Route::get('paiements/statuts', [PaiementController::class, 'statuts']);
    Route::get('paiements/historique', [PaiementController::class, 'historique']);
    Route::middleware('role:DRH')->group(function () {
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
        Route::get('rapports/export', [RapportController::class, 'export']);
    });

    // Audit - DIRECTEUR uniquement
    Route::middleware('role:DIRECTEUR')->group(function () {
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/{audit}', [AuditController::class, 'show']);
    });
});
