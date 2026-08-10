<?php

use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;

/*
 * COHÉRENCE DES DEUX CIRCUITS DE VALIDATION.
 *
 * L'application expose deux voies vers le statut VALIDE d'un OM :
 *
 *   1. Circuit par ÉTAT (canonique) — EtatPaiementService : le document signé
 *      de la mission (récap → CONTROLE → VISA_DAF → VALIDE) ; sa validation
 *      propage VALIDE aux OM. C'est le circuit officiel de l'ANaTT.
 *   2. Circuit par OM (repli) — ValidationController/MissionService : validation
 *      unitaire d'un OM, réservée aux missions SANS état (reprise de données,
 *      correction ponctuelle d'un import historique).
 *
 * Ils ne sont PAS une double source de vérité : ils sont mutuellement exclusifs
 * PAR MISSION. Dès qu'un état existe, le circuit par OM est fermé — sinon le
 * statut de l'OM pourrait diverger de celui du document signé. Ces tests
 * VERROUILLENT cette exclusivité (invariant non couvert ailleurs) ; la porte de
 * paiement unifiée est, elle, couverte par Paiement/BarriereEtatTest.
 *
 * Cf. docs/circuits-validation.md.
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create();
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();
});

/** Crée une mission + un OM IMPORTE, puis établit l'état (BROUILLON). */
function missionAvecEtat(object $t): Mission
{
    $mission = Mission::factory()->create();
    OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'statut' => 'IMPORTE',
        'montant_total' => 135000, 'avance' => 94500, 'reliquat' => 40500,
    ]);
    $t->actingAs($t->gestionnaire)
        ->postJson("/api/missions/{$mission->id}/etat/recapituler")->assertOk();

    return $mission;
}

// ── L'existence d'un état ferme le circuit par OM (exclusivité) ──

it('refuse de valider un OM dès qu’un état existe', function () {
    $mission = missionAvecEtat($this);
    $om = $mission->ordresMission()->first();

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertStatus(422)
        ->assertJsonValidationErrors('etat');
});

it('refuse de rejeter un OM dès qu’un état existe', function () {
    $mission = missionAvecEtat($this);
    $om = $mission->ordresMission()->first();

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/rejeter", [
        'motif' => 'Motif de rejet suffisamment long.',
    ])->assertStatus(422)->assertJsonValidationErrors('etat');
});

// ── Sans état, le circuit par OM reste ouvert (repli / reprise de données) ──

it('autorise la validation par OM tant qu’aucun état n’existe', function () {
    // Circuit OM en une étape : le contrôleur valide directement (IMPORTE → VALIDE).
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertOk()
        ->assertJsonPath('data.statut', 'VALIDE');
});

it('VALIDE garde le même sens dans les deux circuits (OM payable)', function () {
    // Voie ÉTAT : la validation finale du DG propage VALIDE aux OM de la mission.
    $mission = missionAvecEtat($this);
    $etat = $mission->etatsPaiement()->firstOrFail();

    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $this->actingAs($this->dg)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'ETAT-COH-1'])->assertOk();
    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    expect($mission->ordresMission()->first()->fresh()->statut)->toBe('VALIDE');
});
