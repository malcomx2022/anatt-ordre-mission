<?php

use App\Models\Bareme;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OmAmendement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * Amendement d'un OM (rallonge / réduction des jours facturés) — Phase 2.
 * Base : ORDRE_1 / INTERIEUR / journée complète (45 000). OM à 6 j = 270 000,
 * avance 189 000 (70 %), reliquat 81 000. Cf. docs/amendement-om.md.
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();

    Bareme::factory()->create([
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);

    $this->mission = Mission::factory()->create([
        'zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false,
    ]);
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE',
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 6, 'montant_total' => 270000, 'avance' => 189000, 'reliquat' => 81000,
    ]);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'AVANCE', 'montant' => 189000, 'statut' => 'EN_ATTENTE']);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'RELIQUAT', 'montant' => 81000, 'statut' => 'EN_ATTENTE']);
});

/** Poste un amendement sur l'OM du contexte. */
function amenderOm(object $t, array $body, ?User $as = null)
{
    return $t->actingAs($as ?? $t->gestionnaire)->postJson("/api/ordres/{$t->om->id}/amender", $body);
}

function montantVersement(int $omId, string $type): int
{
    return (int) Versement::where('ordre_mission_id', $omId)->where('type', $type)->value('montant');
}

// ── Avant engagement de l'avance : recalcul complet 70/30 ──

it('amende avant engagement : recalcule avance ET reliquat', function () {
    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Rallonge de la mission de deux jours.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->nb_jours)->toBe(8)
        ->and($om->montant_total)->toBe(360000)
        ->and($om->avance)->toBe(252000)   // 70 %
        ->and($om->reliquat)->toBe(108000);

    expect(montantVersement($om->id, 'AVANCE'))->toBe(252000)
        ->and(montantVersement($om->id, 'RELIQUAT'))->toBe(108000);

    $amd = OmAmendement::where('ordre_mission_id', $om->id)->firstOrFail();
    expect($amd->nb_jours_avant)->toBe(6)
        ->and($amd->nb_jours_apres)->toBe(8)
        ->and($amd->delta_montant)->toBe(90000);
});

// ── Après engagement : avance figée, reliquat variable ──

it('amende après engagement : fige l’avance et augmente le reliquat (rallonge)', function () {
    $this->om->update(['avance_engagee' => 189000]);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Rallonge après paiement de l’avance.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->montant_total)->toBe(360000)
        ->and($om->avance)->toBe(189000)      // figée
        ->and($om->reliquat)->toBe(171000);   // 360000 - 189000

    expect(montantVersement($om->id, 'AVANCE'))->toBe(189000)   // inchangé
        ->and(montantVersement($om->id, 'RELIQUAT'))->toBe(171000);
});

it('amende après engagement : réduit le reliquat sans toucher l’avance', function () {
    $this->om->update(['avance_engagee' => 189000]);

    amenderOm($this, ['nb_jours' => 5, 'motif' => 'Réduction d’une journée de mission.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->montant_total)->toBe(225000)
        ->and($om->avance)->toBe(189000)
        ->and($om->reliquat)->toBe(36000);   // 225000 - 189000
});

it('produit un reliquat NÉGATIF (trop-perçu) en forte réduction', function () {
    $this->om->update(['avance_engagee' => 189000]);

    amenderOm($this, ['nb_jours' => 3, 'motif' => 'Forte réduction de la durée.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->montant_total)->toBe(135000)
        ->and($om->avance)->toBe(189000)
        ->and($om->reliquat)->toBe(-54000);

    expect(montantVersement($om->id, 'RELIQUAT'))->toBe(-54000);
});

// ── Le trop-perçu ne part jamais dans un fichier ──

it('exclut un reliquat ≤ 0 du périmètre payable', function () {
    $this->om->update(['statut' => 'VALIDE']);

    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->update(['montant' => -54000]);
    expect(Versement::eligiblesLot($this->mission->id, 'RELIQUAT', 'MTN')->count())->toBe(0);

    // Contrôle : un reliquat positif redevient éligible (le filtre est bien sur le signe).
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->update(['montant' => 40500]);
    expect(Versement::eligiblesLot($this->mission->id, 'RELIQUAT', 'MTN')->count())->toBe(1);
});

// ── Gardes (périmètre v1) ──

it('refuse d’amender un OM rejeté', function () {
    $this->om->update(['statut' => 'REJETE']);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Tentative sur un OM rejeté.'])
        ->assertStatus(422)->assertJsonValidationErrors('statut');
});

it('refuse l’amendement quand l’état des reliquats est validé (hors périmètre v1)', function () {
    $this->om->update(['statut' => 'VALIDE', 'avance_engagee' => 189000]);
    EtatPaiement::create([
        'mission_id' => $this->mission->id, 'tranche' => 'RELIQUAT', 'statut' => 'VALIDE',
        'nb_beneficiaires' => 1, 'montant_total' => 81000, 'montant_avance' => 0, 'montant_reliquat' => 81000,
    ]);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Amendement tardif interdit.'])
        ->assertStatus(422)->assertJsonValidationErrors('etat');
});

it('refuse l’amendement quand l’état des avances est en cours de signature', function () {
    EtatPaiement::create([
        'mission_id' => $this->mission->id, 'tranche' => 'AVANCE', 'statut' => 'CONTROLE',
        'nb_beneficiaires' => 1, 'montant_total' => 189000, 'montant_avance' => 189000, 'montant_reliquat' => 0,
    ]);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Amendement pendant la signature.'])
        ->assertStatus(422)->assertJsonValidationErrors('etat');
});

it('exige un motif d’au moins 10 caractères', function () {
    amenderOm($this, ['nb_jours' => 8, 'motif' => 'court'])
        ->assertStatus(422)->assertJsonValidationErrors('motif');
});

// ── Contrat exposé à la fiche détail (consommé par le frontend) ──

it('expose l’avance engagée et le journal des amendements sur la fiche OM', function () {
    $this->om->update(['avance_engagee' => 189000]);
    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Rallonge documentée pour la fiche.'])->assertOk();

    $this->actingAs($this->gestionnaire)
        ->getJson("/api/ordres/{$this->om->id}")
        ->assertOk()
        ->assertJsonPath('data.avance_engagee', 189000)
        ->assertJsonPath('data.amendements.0.nb_jours_avant', 6)
        ->assertJsonPath('data.amendements.0.nb_jours_apres', 8)
        ->assertJsonPath('data.amendements.0.delta_montant', 90000);
});
