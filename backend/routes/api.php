<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BaremeController;
use App\Http\Controllers\Api\EtatPaiementController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\RapportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SignatureController;
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
// ForcePasswordChange bloque l'API tant qu'un changement de mot de passe est requis
// (création/réinitialisation administrative), sauf l'échappatoire de changement.
Route::middleware(['auth:sanctum', 'throttle:api', \App\Http\Middleware\ForcePasswordChange::class])->group(function () {

    // Auth (tous les utilisateurs connectés)
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    // Permissions de l'utilisateur courant (pilote l'UI du frontend).
    Route::get('auth/me/permissions', [AuthController::class, 'permissions']);
    // Changement de mot de passe par l'utilisateur lui-même (lève must_change_password).
    Route::post('auth/mot-de-passe/changer', [AuthController::class, 'changePassword']);

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
        // Épreuve filigranée, consultable avant toute validation.
        Route::get('ordres/{ordre}/pdf/brouillon', [MissionController::class, 'pdfBrouillon']);
        Route::get('ordres/{ordre}/historique', [MissionController::class, 'historique']);
    });

    // Missions (retranscription SEMO : 1 mission + N OM)
    Route::post('missions', [MissionController::class, 'storeMission'])->middleware('permission:om.retranscrire');
    // Amendement d'un OM (rallonge/réduction des jours facturés). L'avance engagée
    // ne bouge plus ; le reliquat absorbe l'écart. Cf. docs/amendement-om.md.
    Route::post('ordres/{ordre}/amender', [MissionController::class, 'amender'])->middleware('permission:om.retranscrire');

    // ── ÉTAT DE PAIEMENT d'une mission : l'objet que le circuit valide ──
    // Un état = 1 mission, N bénéficiaires, colonnes AVANCE et RELIQUAT.
    // Sa validation finale par le DG ouvre la génération des fichiers de paiement.
    Route::middleware('permission:om.consulter')->group(function () {
        // Liste des missions : point d'entrée du circuit (N OM + 1 état).
        Route::get('missions', [MissionController::class, 'missions']);
        // File de travail du circuit, filtrable par statut.
        Route::get('etats', [EtatPaiementController::class, 'index']);
        Route::get('missions/{mission}/etat', [EtatPaiementController::class, 'show']);
        Route::get('missions/{mission}/etat/brouillon', [EtatPaiementController::class, 'brouillon']);
        Route::get('missions/{mission}/etat/telecharger', [EtatPaiementController::class, 'telecharger']);
        // État général consolidé : servi seulement quand les deux tranches sont validées.
        Route::get('missions/{mission}/etat/final', [EtatPaiementController::class, 'telechargerFinal']);
    });
    // Récapitulatif : produit ou rafraîchit le brouillon de l'état.
    Route::post('missions/{mission}/etat/recapituler', [EtatPaiementController::class, 'recapituler'])
        ->middleware('permission:om.retranscrire');
    // Numérotation manuelle, selon le registre papier de l'agence.
    Route::put('etats/{etat}/numero', [EtatPaiementController::class, 'definirNumero'])
        ->middleware('permission:om.numeroter');
    // Étape 1 — contrôle de conformité.
    Route::post('etats/{etat}/controler', [EtatPaiementController::class, 'controler'])
        ->middleware('permission:om.valider');
    // Étape 2 — visa du DAF.
    Route::post('etats/{etat}/viser', [EtatPaiementController::class, 'viser'])
        ->middleware('permission:om.viser');
    // Étape 3 — validation finale du DG : verrouille l'état, ouvre le paiement.
    Route::post('etats/{etat}/approuver', [EtatPaiementController::class, 'approuver'])
        ->middleware('permission:om.approuver');
    // Rejet motivé, ouvert aux trois acteurs.
    Route::post('etats/{etat}/rejeter', [EtatPaiementController::class, 'rejeter'])
        ->middleware('permission:om.rejeter');

    // Validation OM par OM (missions SANS état : reprise de données, correction
    // unitaire). Une seule étape IMPORTE -> VALIDE ; refusée dès qu'un état existe,
    // pour garder une seule source de vérité sur le statut. Le circuit à trois
    // signatures se joue sur l'état (cf. /etats/*). Cf. docs/circuits-validation.md.
    Route::post('ordres/{ordre}/valider', [ValidationController::class, 'valider'])
        ->middleware('permission:om.valider');
    // Rejet motivé, depuis IMPORTE.
    Route::post('ordres/{ordre}/rejeter', [ValidationController::class, 'rejeter'])
        ->middleware('permission:om.rejeter');

    // Paiements (mode GÉNÉRATION)
    Route::middleware('permission:paiements.consulter')->group(function () {
        Route::get('paiements/lots', [PaiementController::class, 'index']);
        Route::get('paiements/lots/previsualiser', [PaiementController::class, 'previsualiser']);
        Route::get('paiements/statuts', [PaiementController::class, 'statuts']);
        Route::get('paiements/historique', [PaiementController::class, 'historique']);
        Route::get('paiements/lots/{lot}/download', [PaiementController::class, 'download']);
        Route::get('paiements/lots/job/{trackingId}', [PaiementController::class, 'statutJob']);
        // Régularisations (trop-perçus à récupérer) — consultation.
        Route::get('paiements/regularisations', [PaiementController::class, 'regularisations']);
    });
    Route::middleware('throttle:paiement')->group(function () {
        Route::post('paiements/lots/generer', [PaiementController::class, 'generer'])->middleware('permission:paiements.generer');
        Route::post('paiements/lots/{lot}/erreur', [PaiementController::class, 'marquerErreur'])->middleware('permission:paiements.generer');
        Route::post('paiements/lots/{lot}/confirmer', [PaiementController::class, 'confirmer'])->middleware('permission:paiements.confirmer');
        // Régularisation recouvrée (retenue appliquée par la RH) — constat manuel.
        Route::post('paiements/regularisations/{versement}/recouvrer', [PaiementController::class, 'recouvrer'])->middleware('permission:paiements.confirmer');
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
            // Signatures électroniques des signataires du circuit (nom, prénoms,
            // fonction et image scannée). L'image n'est jamais renvoyée par l'API.
            Route::get('signatures', [SignatureController::class, 'index']);
            Route::post('signatures/{poste}', [SignatureController::class, 'store']);
            Route::delete('signatures/{poste}', [SignatureController::class, 'destroy']);

            // Paramètres système (liste blanche de clés, secret SMTP chiffré/masqué).
            Route::get('settings', [SettingsController::class, 'show']);
            Route::put('settings', [SettingsController::class, 'update']);

            // Sauvegardes de la base de données.
            Route::get('backups', [BackupController::class, 'index']);
            Route::post('backups', [BackupController::class, 'store']);
            Route::delete('backups/{name}', [BackupController::class, 'destroy']);
        });

        // Pourcentage d'avance paramétrable (lecture publique, écriture SUPER_ADMIN).
        Route::get('parametres/part-avance', [SettingsController::class, 'partAvance']);
    });
});
