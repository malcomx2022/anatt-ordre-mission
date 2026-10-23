<?php

use App\Models\AuditLog;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * Circuit de validation OM par OM — voie de REPLI (missions sans état de paiement).
 * Une seule étape : IMPORTE ──valider (CONTROLEUR, om.valider)──▶ VALIDE (verrouillé,
 * payable). Le circuit à trois signatures (contrôle → DAF → DG) se joue sur l'ÉTAT
 * de la mission (cf. EtatPaiementTest / CoherenceCircuitsTest).
 */

beforeEach(function () {
    $this->controleur = User::factory()->controleur()->create(['name' => 'Paul AGBO']);
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();
});

// ── Chemin nominal : une seule étape ──

it('valide un OM en une étape et le trace (signature de contrôle)', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertOk()->assertJsonPath('data.statut', 'VALIDE');

    $om->refresh();

    expect($om->verifie_nom)->toBe('Paul AGBO')
        ->and($om->signature_verif_hash)->toHaveLength(64)
        ->and($om->verifie_le)->not->toBeNull();
});

it('solde à la validation un OM exonéré (montant nul), comme le circuit par état', function () {
    // Prise en charge par un tiers : montants à 0, aucun versement — rien à payer.
    $om = OrdreMission::factory()->create([
        'statut' => 'IMPORTE', 'montant_total' => 0, 'avance' => 0, 'reliquat' => 0,
    ]);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")->assertOk();

    expect($om->fresh()->statut)->toBe('SOLDE');
});

it('journalise la validation en une étape', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")->assertOk();

    $actions = AuditLog::where('entite', 'ordre_mission')->where('entite_id', $om->id)->pluck('action');

    expect($actions)->toContain('VALIDATE_OM')
        ->and($actions)->not->toContain('VISA_DAF_OM')
        ->and($actions)->not->toContain('APPROVE_DG_OM');
});

// ── Les étapes DAF/DG n'existent plus OM par OM ──

it('n’expose plus les endpoints OM de visa DAF et d’approbation DG', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")->assertNotFound();
    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/approuver")->assertNotFound();
});

// ── Transitions contraintes ──

it('refuse de valider un OM qui n’est pas IMPORTE', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VALIDE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertStatus(422);
});

// ── Cloisonnement des rôles ──

it('interdit à un profil sans om.valider de valider', function () {
    // Le DAF/DG portent le circuit de l'état, pas la validation OM par OM.
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/valider")->assertForbidden();
    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/valider")->assertForbidden();
});

// ── Rejet motivé depuis IMPORTE ──

it('autorise le rejet motivé depuis IMPORTE', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/rejeter", [
        'motif' => 'Pièces justificatives incomplètes au dossier.',
    ])->assertOk()->assertJsonPath('data.statut', 'REJETE');

    expect($om->fresh()->commentaire_rejet)->toContain('justificatives');
});

it('exige un motif d’au moins 10 caractères pour rejeter', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/rejeter", ['motif' => 'court'])
        ->assertStatus(422)->assertJsonValidationErrors('motif');
});

// ── Le verrouillage est bien la porte du paiement ──

it('ne rend l’OM payable qu’après sa validation', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id,
        'type' => 'AVANCE',
        'operateur' => 'MTN',
        'statut' => 'EN_ATTENTE',
    ]);

    $payable = fn () => Versement::eligiblesLot($om->mission_id, 'AVANCE', 'MTN')->count();

    expect($payable())->toBe(0);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")->assertOk();

    expect($payable())->toBe(1);
});
