<?php

use App\Jobs\SendNotification;
use App\Models\Bareme;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Versement;
use App\Services\MissionService;
use App\Services\NotificationService;
use App\Support\PermissionRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->service = app(MissionService::class);
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

it('notifie l’agent financier à la validation d’un OM', function () {
    $financier = User::factory()->agentFinancier()->create();
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    // E-mail (job) ET notification in-app persistée pour l'agent financier.
    Queue::assertPushed(SendNotification::class);
    expect(Notification::pour($financier->id)->where('type', 'om_valide')->count())->toBe(1);
});

it('notifie le gestionnaire OM au rejet d’un OM', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->rejeter($om, 'Document non conforme', $this->gestion);

    Queue::assertPushed(SendNotification::class);
});

it('n’envoie aucune notification si aucun destinataire du rôle', function () {
    // Aucun agent_financier en base -> aucune notification à la validation.
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    Queue::assertNotPushed(SendNotification::class);
});

it('ne notifie pas un compte désactivé du rôle', function () {
    // Un compte désactivé ne se connecte plus : il ne doit recevoir ni la notification
    // in-app ni l'e-mail (montants, mission), données réservées aux comptes actifs.
    $actif = User::factory()->agentFinancier()->create();
    $inactif = User::factory()->agentFinancier()->create(['actif' => false]);
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    expect(Notification::pour($actif->id)->count())->toBe(1)
        ->and(Notification::pour($inactif->id)->count())->toBe(0);
    Queue::assertPushed(SendNotification::class, 1);
});

it('n’envoie pas l’e-mail d’un compte désactivé entre-temps (file d’attente)', function () {
    Mail::shouldReceive('raw')->never();
    $user = User::factory()->agentFinancier()->create(['actif' => false]);

    (new SendNotification($user->id, 'om_valide', ['reference_semo' => 'SEMO-1', 'beneficiaire' => 'DADA Richard', 'montant' => '45 000']))->handle();

});

// ── Trop-perçu (amendement) ──

/** OM avec avance ENGAGÉE (189 000) prêt à basculer en trop-perçu sur forte réduction. */
function omEngageAvecReliquat(): OrdreMission
{
    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);
    $mission = Mission::factory()->create(['zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false]);
    $om = OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'statut' => 'VALIDE',
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 6, 'montant_total' => 270000, 'avance' => 189000, 'reliquat' => 81000, 'avance_engagee' => 189000,
    ]);
    Versement::factory()->create(['ordre_mission_id' => $om->id, 'type' => 'RELIQUAT', 'montant' => 81000, 'statut' => 'EN_ATTENTE']);

    return $om;
}

it('notifie l’agent financier quand un amendement produit un trop-perçu', function () {
    User::factory()->agentFinancier()->create();
    $om = omEngageAvecReliquat();

    // 3 j → 135 000 ; reliquat = 135 000 − 189 000 = −54 000 (trop-perçu).
    $this->service->amender($om, ['nb_jours' => 3, 'motif' => 'Forte réduction de la durée de mission.'], $this->gestion);

    Queue::assertPushed(SendNotification::class);
});

it('n’émet aucune notification de trop-perçu quand le reliquat reste positif', function () {
    User::factory()->agentFinancier()->create();
    $om = omEngageAvecReliquat();

    // 8 j → 360 000 ; reliquat = 360 000 − 189 000 = 171 000 (positif).
    $this->service->amender($om, ['nb_jours' => 8, 'motif' => 'Rallonge de la mission de deux jours.'], $this->gestion);

    Queue::assertNotPushed(SendNotification::class);
});

// ── Destinataires selon les droits effectifs (décision D1) ──

it('notifie un rôle personnalisé qui détient la permission de l’action', function () {
    app(PermissionRepository::class)->creerRole('PAYEUR_REGIONAL', 'Payeur régional', [
        'om.consulter', 'paiements.consulter', 'paiements.generer', 'paiements.confirmer',
    ]);
    $payeur = User::factory()->create(['role' => 'PAYEUR_REGIONAL']);
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    expect(Notification::pour($payeur->id)->where('type', 'om_valide')->count())->toBe(1);
});

it('notifie un compte à qui la permission est accordée individuellement', function () {
    $lecteur = User::factory()->lecteur()->create();
    UserPermission::create(['user_id' => $lecteur->id, 'permission' => 'paiements.generer', 'granted' => true]);
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    expect(Notification::pour($lecteur->id)->where('type', 'om_valide')->count())->toBe(1);
});

it('ne notifie plus un membre du profil à qui la permission est retirée', function () {
    $financier = User::factory()->agentFinancier()->create();
    UserPermission::create(['user_id' => $financier->id, 'permission' => 'paiements.generer', 'granted' => false]);
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    expect(Notification::pour($financier->id)->count())->toBe(0);
    Queue::assertNotPushed(SendNotification::class);
});

it('ne notifie pas le super admin, dont l’accès implicite n’est pas une responsabilité', function () {
    $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    expect(Notification::pour($superAdmin->id)->count())->toBe(0);
});

it('ne notifie qu’une fois un compte visé à deux titres (lot confirmé)', function () {
    // Gestionnaire OM à qui l'on a aussi accordé utilisateurs.gerer : il relève des
    // deux destinataires du lot confirmé, mais ne reçoit qu'une notification.
    UserPermission::create(['user_id' => $this->gestion->id, 'permission' => 'utilisateurs.gerer', 'granted' => true]);
    $admin = User::factory()->admin()->create();
    $lot = LotPaiement::create([
        'mission_id' => Mission::factory()->create()->id, 'tranche' => 'AVANCE',
        'operateur' => 'MTN', 'statut' => 'CONFIRME', 'montant_total' => 45000,
    ]);

    app(NotificationService::class)->lotConfirme($lot);

    expect(Notification::pour($this->gestion->id)->where('type', 'lot_confirme')->count())->toBe(1)
        ->and(Notification::pour($admin->id)->where('type', 'lot_confirme')->count())->toBe(1);
});
