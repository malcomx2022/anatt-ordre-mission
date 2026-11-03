<?php

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\EtatPaiementService;
use App\Services\MissionService;
use Database\Seeders\BaremeSeeder;
use Database\Seeders\ZoneSeeder;

/*
 * L'état de paiement d'une mission doit rester en phase avec ses OM sans
 * intervention manuelle : dès qu'un OM change de montant ou est rejeté,
 * l'état BROUILLON / REJETE se recale via OrdreMissionObserver.
 *
 * Verrou complémentaire : un état signé (CONTROLE / VISA_DAF / VALIDE) ne
 * doit JAMAIS être altéré par un changement d'OM — les instantanés
 * financiers sont scellés dès la première signature.
 */

beforeEach(function () {
    $this->seed(ZoneSeeder::class);
    $this->seed(BaremeSeeder::class);
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

/**
 * Retranscription d'une mission avec N agents actifs (per diem non nul).
 *
 * @param  int  $nbAgents  nombre d'OM à créer
 */
function missionAvecOrdres(User $user, int $nbAgents = 3): Mission
{
    $agents = Agent::factory()->count($nbAgents)->create([
        'indice' => 500,
        'telephone' => '0190000000',
        'operateur' => 'MTN',
    ]);

    return app(MissionService::class)->creerAvecOrdres([
        'objet' => 'Mission de contrôle terrain',
        'destination' => 'Parakou',
        'zone_code' => 'INTERIEUR',
        'date_depart' => now()->subDays(4)->toDateString(),
        'date_retour' => now()->subDays(2)->toDateString(),
        'prise_en_charge_tiers' => false,
        'ordres' => $agents->values()->map(fn (Agent $a, int $i) => [
            'reference_semo' => sprintf('AUTOSYNC-%03d', $i + 1),
            'agent_id' => $a->id,
            'role' => $i === $nbAgents - 1 ? 'CAV' : 'PARTICIPANT',
            'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        ])->all(),
    ], $user);
}

it('resynchronise un état BROUILLON quand un OM est rejeté', function () {
    $mission = missionAvecOrdres($this->gestion, 3);
    $etat = app(EtatPaiementService::class)->recapituler($mission, $this->gestion);

    // Instantané initial : 3 bénéficiaires, montant strictement positif.
    $totalInitial = $etat->montant_total;
    expect($etat->nb_beneficiaires)->toBe(3)
        ->and($totalInitial)->toBeGreaterThan(0);

    // Rejet direct (sans passer par le service, qui bloque l'action sur un
    // OM d'une mission ayant déjà un état) : on veut isoler le mécanisme de
    // l'observer, pas la règle métier d'exigerAbsenceDEtat.
    $omARejeter = $mission->ordresMission()->first();
    $omARejeter->update(['statut' => 'REJETE']);

    // L'observer a resynchronisé après commit : l'état ne contient plus que
    // les 2 OM restants.
    $etat->refresh();
    expect($etat->nb_beneficiaires)->toBe(2)
        ->and($etat->montant_total)->toBeLessThan($totalInitial)
        ->and($etat->montant_total)->toBeGreaterThan(0);
});

it('ne touche PAS un état signé (VALIDE) quand un OM change', function () {
    $mission = missionAvecOrdres($this->gestion, 2);
    $etat = app(EtatPaiementService::class)->recapituler($mission, $this->gestion);
    $totalScelle = $etat->montant_total;
    $nbInitial = $etat->nb_beneficiaires;

    // On simule un état signé sans dérouler tout le circuit : on écrit
    // directement le statut VALIDE en base pour verrouiller l'invariant.
    $etat->update(['statut' => 'VALIDE']);

    // Un OM change : l'observer déclenche synchroniser(), qui doit détecter
    // le statut signé et ne rien faire.
    $mission->ordresMission()->first()->update(['statut' => 'ANNULE']);

    $etat->refresh();
    expect($etat->statut)->toBe('VALIDE')
        ->and($etat->montant_total)->toBe($totalScelle)
        ->and($etat->nb_beneficiaires)->toBe($nbInitial);
});

it('recale un état REJETE quand un nouvel OM est créé pour la mission', function () {
    $mission = missionAvecOrdres($this->gestion, 2);
    $etat = app(EtatPaiementService::class)->recapituler($mission, $this->gestion);

    // On amène l'état à REJETE (comme après un rejet motivé).
    $etat->update(['statut' => 'REJETE', 'commentaire_rejet' => 'Motif ok']);
    $totalAvant = $etat->montant_total;

    // Ajout direct d'un OM à la mission : l'observer resynchronise.
    OrdreMission::create([
        'reference_semo' => 'AUTOSYNC-NEW',
        'mission_id' => $mission->id,
        'agent_id' => Agent::factory()->create(['indice' => 500])->id,
        'role' => 'PARTICIPANT',
        'categorie' => 'INDICE_400_799',
        'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 2,
        'lignes_per_diem' => [['type' => 'JOURNEE_COMPLETE', 'taux' => 15000, 'nb_jours' => 2, 'montant' => 30000]],
        'montant_transport' => 0,
        'montant_total' => 30000,
        'avance' => 21000,
        'reliquat' => 9000,
        'statut' => 'IMPORTE',
    ]);

    $etat->refresh();
    expect($etat->nb_beneficiaires)->toBe(3)
        // L'état des avances se recale sur les AVANCES : +21 000, pas +30 000.
        ->and($etat->montant_total)->toBe($totalAvant + 21000)
        ->and($etat->statut)->toBe('REJETE'); // le statut reste, seuls les montants bougent
});

it('ne crée pas d\'état par accident quand aucun état n\'existe pour la mission', function () {
    $mission = missionAvecOrdres($this->gestion, 2);

    // Aucun recapituler() n'a été appelé : la mission n'a pas d'état.
    expect(EtatPaiement::where('mission_id', $mission->id)->exists())->toBeFalse();

    // Une mutation d'OM déclenche l'observer, qui ne DOIT pas fabriquer
    // un état fantôme — seul recapituler() est autorisé à en créer.
    $mission->ordresMission()->first()->update(['statut' => 'REJETE']);

    expect(EtatPaiement::where('mission_id', $mission->id)->exists())->toBeFalse();
});

it('ne duplique ni l’audit ni la notification sur un récapitulatif sans effet', function () {
    User::factory()->controleur()->create(); // destinataire de « l'état attend signature »
    $mission = missionAvecOrdres($this->gestion, 2);
    $service = app(EtatPaiementService::class);

    $service->recapituler($mission, $this->gestion);
    $audits = AuditLog::where('action', 'RECAP_ETAT')->count();
    $notifications = Notification::count();
    expect($audits)->toBe(1)->and($notifications)->toBeGreaterThan(0);

    // « Rafraîchir » sans aucun changement d'OM : rien de neuf à signaler.
    $etat = $service->recapituler($mission, $this->gestion);
    expect($etat->statut)->toBe('BROUILLON')
        ->and(AuditLog::where('action', 'RECAP_ETAT')->count())->toBe($audits)
        ->and(Notification::count())->toBe($notifications);
});

it('signale de nouveau un récapitulatif qui reprend un état REJETE', function () {
    User::factory()->controleur()->create();
    $mission = missionAvecOrdres($this->gestion, 2);
    $service = app(EtatPaiementService::class);
    $etat = $service->recapituler($mission, $this->gestion);
    $notifications = Notification::count();

    $etat->update(['statut' => 'REJETE', 'commentaire_rejet' => 'Montants a verifier']);

    // Mêmes montants, mais l'état repasse REJETE → BROUILLON : le circuit repart.
    $etat = $service->recapituler($mission, $this->gestion);
    expect($etat->statut)->toBe('BROUILLON')
        ->and($etat->commentaire_rejet)->toBeNull()
        ->and(AuditLog::where('action', 'RECAP_ETAT')->count())->toBe(2)
        ->and(Notification::count())->toBeGreaterThan($notifications);
});

it('conserve le motif d’un état REJETE recalé par un amendement, jusqu’au récapitulatif', function () {
    $mission = missionAvecOrdres($this->gestion, 2);
    $service = app(EtatPaiementService::class);
    $etat = $service->recapituler($mission, $this->gestion);
    $etat->update(['statut' => 'REJETE', 'commentaire_rejet' => 'Nombre de jours a corriger']);
    $totalAvant = $etat->montant_total;

    // Les montants changent réellement (réduction d'un OM) : l'observer recale.
    $om = $mission->ordresMission()->first();
    $om->update(['montant_total' => 0, 'avance' => 0, 'reliquat' => 0]);

    $etat->refresh();
    expect($etat->montant_total)->toBeLessThan($totalAvant)
        // Comportement retenu : le rejet reste un fait, motif compris.
        ->and($etat->statut)->toBe('REJETE')
        ->and($etat->commentaire_rejet)->toBe('Nombre de jours a corriger');

    // Le récapitulatif relance le circuit et efface le motif.
    $etat = $service->recapituler($mission, $this->gestion);
    expect($etat->statut)->toBe('BROUILLON')->and($etat->commentaire_rejet)->toBeNull();
});
