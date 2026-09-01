<?php

use App\Models\Bareme;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\EtatPaiementService;
use App\Services\MissionService;

/*
 * Intégration de l'amendement avec l'état des reliquats (Phase 4).
 *
 * On vérifie les invariants d'intégrité quand un OM est amendé après validation
 * de l'état des AVANCES :
 *   - un état des reliquats en BROUILLON reflète l'amendement (montant signé,
 *     éventuellement négatif) via l'observer ;
 *   - l'état des AVANCES validé reste scellé (HMAC) : ses montants ne bougent pas ;
 *   - un état des reliquats en cours de signature n'est pas altéré et l'amendement
 *     est refusé avec un message qui guide vers le rejet ;
 *   - un état des reliquats rejeté se rétablit sur les montants amendés.
 * Cf. docs/amendement-om.md (Phase 4).
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create();
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();

    Bareme::factory()->create([
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);

    $this->mission = Mission::factory()->create([
        'zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false,
    ]);
    // OM 6 j = 270 000, avance 189 000 (70 %), reliquat 81 000.
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE',
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 6, 'montant_total' => 270000, 'avance' => 189000, 'reliquat' => 81000,
    ]);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'AVANCE', 'montant' => 189000, 'statut' => 'EN_ATTENTE']);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'RELIQUAT', 'montant' => 81000, 'statut' => 'EN_ATTENTE']);
});

/** Déroule le circuit complet de l'état des AVANCES → VALIDE (fige avance_engagee). */
function validerAvances(object $t): EtatPaiement
{
    $svc = app(EtatPaiementService::class);
    $etat = $svc->recapituler($t->mission, $t->gestionnaire);          // BROUILLON
    $svc->controler($etat->fresh(), $t->controleur);                   // CONTROLE
    $svc->viser($etat->fresh(), $t->daf);                              // VISA_DAF
    $svc->definirNumero($etat->fresh(), 'AV-2026-001', $t->dg);
    $svc->approuver($etat->fresh(), $t->dg);                           // VALIDE

    return $etat->fresh();
}

/** Établit l'état des reliquats en BROUILLON (avances déjà validées). */
function reliquatBrouillon(object $t): EtatPaiement
{
    return app(EtatPaiementService::class)->recapituler($t->mission, $t->gestionnaire, EtatPaiement::TRANCHE_RELIQUAT);
}

function amender(object $t, array $data): void
{
    app(MissionService::class)->amender($t->om->fresh(), $data, $t->gestionnaire);
}

it('reflète l’amendement (total négatif signé) sur l’état des reliquats en BROUILLON', function () {
    validerAvances($this);
    $reliquat = reliquatBrouillon($this);
    expect($reliquat->montant_reliquat)->toBe(81000)->and($reliquat->montant_total)->toBe(81000);

    // Forte réduction : reliquat = 135 000 − 189 000 = −54 000 (trop-perçu).
    amender($this, ['nb_jours' => 3, 'motif' => 'Forte réduction de la durée de mission.']);

    $reliquat->refresh();
    expect($reliquat->montant_reliquat)->toBe(-54000)
        ->and($reliquat->montant_total)->toBe(-54000)
        ->and($reliquat->statut)->toBe('BROUILLON');
});

it('laisse l’état des AVANCES validé scellé et inchangé après un amendement', function () {
    $avance = validerAvances($this);
    $hashScelle = $avance->signature_dg_hash;
    $montantAvance = $avance->montant_avance;

    amender($this, ['nb_jours' => 3, 'motif' => 'Réduction sans effet sur l’avance figée.']);

    $avance->refresh();
    // L'avance est figée : montants et scellé HMAC identiques → document intègre.
    expect($avance->statut)->toBe('VALIDE')
        ->and($avance->montant_avance)->toBe($montantAvance)
        ->and($avance->montant_total)->toBe($montantAvance)
        ->and($avance->signature_dg_hash)->toBe($hashScelle);
});

it('n’altère pas un état des reliquats en cours de signature et refuse l’amendement', function () {
    validerAvances($this);
    $reliquat = reliquatBrouillon($this);
    app(EtatPaiementService::class)->controler($reliquat->fresh(), $this->controleur); // CONTROLE
    $montantScelle = $reliquat->fresh()->montant_reliquat;

    $this->actingAs($this->gestionnaire)
        ->postJson("/api/ordres/{$this->om->id}/amender", ['nb_jours' => 3, 'motif' => 'Tentative pendant la signature.'])
        ->assertStatus(422)->assertJsonValidationErrors('etat');

    // L'OM n'a pas bougé, l'état signé non plus.
    expect($this->om->fresh()->nb_jours)->toBe(6)
        ->and($reliquat->fresh()->montant_reliquat)->toBe($montantScelle)
        ->and($reliquat->fresh()->statut)->toBe('CONTROLE');
});

it('rétablit un état des reliquats rejeté sur les montants amendés', function () {
    validerAvances($this);
    $reliquat = reliquatBrouillon($this);
    app(EtatPaiementService::class)->rejeter($reliquat->fresh(), 'Motif de rejet suffisant.', $this->controleur); // REJETE

    // Rallonge : reliquat = 360 000 − 189 000 = 171 000.
    amender($this, ['nb_jours' => 8, 'motif' => 'Rallonge documentée de la mission.']);

    $reetabli = app(EtatPaiementService::class)->recapituler($this->mission, $this->gestionnaire, EtatPaiement::TRANCHE_RELIQUAT);
    expect($reetabli->statut)->toBe('BROUILLON')
        ->and($reetabli->montant_reliquat)->toBe(171000)
        ->and($reetabli->montant_total)->toBe(171000);
});
