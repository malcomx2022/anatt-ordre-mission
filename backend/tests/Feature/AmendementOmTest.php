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
 * Base : INDICE_SUP_800 / INTERIEUR / journée complète (45 000). OM à 6 j = 270 000,
 * avance 189 000 (70 %), reliquat 81 000. Cf. docs/amendement-om.md.
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();

    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);

    $this->mission = Mission::factory()->create([
        'zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false,
    ]);
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE',
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
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

it('refuse l’amendement tant qu’un fichier contenant l’OM attend son dépôt', function () {
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'AVANCE')->update(['statut' => 'GENERE']);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Rallonge de la mission de deux jours.'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('versement');

    expect(montantVersement($this->om->id, 'AVANCE'))->toBe(189000);
});

it('circuit par OM : une avance déjà payée est engagée à l’amendement, le reliquat absorbe l’écart', function () {
    // Mission sans état (repli par OM) : avance_engagee n'a jamais été posée,
    // mais l'avance de 189 000 est déjà confirmée sur la plateforme.
    $this->om->update(['statut' => 'VALIDE']);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'AVANCE')->update(['statut' => 'CONFIRME']);

    amenderOm($this, ['nb_jours' => 2, 'motif' => 'Retour anticipé constaté par avenant.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->montant_total)->toBe(90000)
        ->and($om->avance_engagee)->toBe(189000)
        ->and($om->avance)->toBe(189000)       // ce qui a été versé
        ->and($om->reliquat)->toBe(-99000);    // trop-perçu = 90 000 − 189 000

    expect(montantVersement($om->id, 'AVANCE'))->toBe(189000)
        ->and(Versement::where('ordre_mission_id', $om->id)->where('type', 'RELIQUAT')->value('statut'))->toBe('A_RECOUVRER');
});

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

it('circuit par OM : refuse d’amender un OM dont le reliquat est déjà payé', function () {
    // Mission sans état, avance ET reliquat confirmés (OM soldé). Le reliquat versé
    // ne peut plus être réécrit : amender ferait diverger le reliquat de l'OM de ce
    // qui a été payé (complément jamais versé, ou trop-perçu jamais détecté).
    Versement::where('ordre_mission_id', $this->om->id)->update(['statut' => 'CONFIRME']);
    $this->om->update(['statut' => 'SOLDE']);

    amenderOm($this, ['nb_jours' => 3, 'motif' => 'Mission écourtée, retour anticipé constaté.'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('versement');

    $om = $this->om->fresh();
    expect($om->nb_jours)->toBe(6)
        ->and($om->reliquat)->toBe(81000)
        ->and(OmAmendement::where('ordre_mission_id', $om->id)->exists())->toBeFalse();
});

it('refuse d’amender un OM dont le trop-perçu a déjà été recouvré', function () {
    // Trop-perçu soldé : reliquat négatif recouvré (retenue RH appliquée).
    $this->om->update(['avance_engagee' => 189000]);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')
        ->update(['montant' => -54000, 'statut' => 'RECOUVRE']);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Ré-amendement après recouvrement du trop-perçu.'])
        ->assertStatus(422)->assertJsonValidationErrors('versement');

    // Ni l'OM ni le versement recouvré n'ont bougé.
    expect($this->om->fresh()->nb_jours)->toBe(6)
        ->and(montantVersement($this->om->id, 'RELIQUAT'))->toBe(-54000);
});

it('autorise l’amendement tant que le trop-perçu n’est que A_RECOUVRER (non recouvré)', function () {
    // Contrôle : un trop-perçu encore à recouvrer reste amendable (rien n'a été retenu).
    $this->om->update(['avance_engagee' => 189000]);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')
        ->update(['montant' => -54000, 'statut' => 'A_RECOUVRER']);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Correction avant recouvrement du trop-perçu.'])->assertOk();

    // Rallonge : reliquat = 360 000 − 189 000 = 171 000 → redevient payable.
    expect(montantVersement($this->om->id, 'RELIQUAT'))->toBe(171000)
        ->and(Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->value('statut'))->toBe('EN_ATTENTE');
});

it('refuse l’amendement quand l’état des avances est en cours de signature', function () {
    EtatPaiement::create([
        'mission_id' => $this->mission->id, 'tranche' => 'AVANCE', 'statut' => 'CONTROLE',
        'nb_beneficiaires' => 1, 'montant_total' => 189000, 'montant_avance' => 189000, 'montant_reliquat' => 0,
    ]);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Amendement pendant la signature.'])
        ->assertStatus(422)->assertJsonValidationErrors('etat');
});

it('rouvre un OM soldé par une avance à 100 % quand une rallonge recrée un reliquat', function () {
    // Part d'avance à 100 % : l'OM est soldé dès le paiement de son avance (reliquat nul).
    // Une rallonge reste le cas d'usage de l'avenant : le reliquat recréé doit être payable,
    // donc l'OM repasse VALIDE (seul un OM VALIDE entre dans les fichiers).
    $this->om->update([
        'statut' => 'SOLDE', 'avance' => 270000, 'reliquat' => 0, 'avance_engagee' => 270000,
    ]);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'AVANCE')->update(['montant' => 270000, 'statut' => 'CONFIRME']);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->update(['montant' => 0]);

    amenderOm($this, ['nb_jours' => 8, 'motif' => 'Rallonge après paiement intégral de l’avance.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->statut)->toBe('VALIDE')
        ->and($om->reliquat)->toBe(90000)
        ->and(montantVersement($om->id, 'RELIQUAT'))->toBe(90000);
});

it('garde soldé un OM dont l’amendement ne laisse rien à régler', function () {
    // OM soldé (avance à 100 % payée) : un amendement sans effet sur le reliquat ne le rouvre pas.
    $this->om->update([
        'statut' => 'SOLDE', 'avance' => 270000, 'reliquat' => 0, 'avance_engagee' => 270000,
    ]);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'AVANCE')->update(['montant' => 270000, 'statut' => 'CONFIRME']);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->update(['montant' => 0]);

    amenderOm($this, ['nb_jours' => 6, 'motif' => 'Correction sans changement de durée.'])->assertOk();

    expect($this->om->fresh()->statut)->toBe('SOLDE');
});

it('solde l’OM quand l’amendement ramène le reliquat à zéro (avance payée)', function () {
    // 10 j × 35 000 = 350 000 ; avance payée et engagée 245 000 ; 7 j → 245 000.
    Bareme::factory()->create([
        'categorie' => 'INDICE_400_799', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 35000,
    ]);
    $this->om->update([
        'statut' => 'VALIDE', 'categorie' => 'INDICE_400_799', 'nb_jours' => 10,
        'montant_total' => 350000, 'avance' => 245000, 'reliquat' => 105000, 'avance_engagee' => 245000,
    ]);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'AVANCE')->update(['montant' => 245000, 'statut' => 'CONFIRME']);
    Versement::where('ordre_mission_id', $this->om->id)->where('type', 'RELIQUAT')->update(['montant' => 105000]);

    amenderOm($this, ['nb_jours' => 7, 'motif' => 'Mission écourtée à sept jours.'])->assertOk();

    $om = $this->om->fresh();
    expect($om->reliquat)->toBe(0)
        ->and($om->statut)->toBe('SOLDE');
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
