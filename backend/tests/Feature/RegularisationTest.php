<?php

use App\Models\Agent;
use App\Models\Bareme;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\PdfGenerator;

/*
 * Régularisations (trop-perçus) — Phase 3.
 * Un amendement fortement réducteur après engagement de l'avance produit un
 * reliquat NÉGATIF : le versement bascule en A_RECOUVRER, sort du périmètre
 * payable et rejoint la file des régularisations, jusqu'à ce que la RH ait
 * appliqué la retenue (statut RECOUVRE). Cf. docs/amendement-om.md.
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->finance = User::factory()->agentFinancier()->create();

    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);

    $this->mission = Mission::factory()->create([
        'zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false,
    ]);
    $this->agent = Agent::factory()->create(['operateur' => 'MTN']);
    // OM 6 j = 270 000, avance ENGAGÉE à 189 000, reliquat 81 000.
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'agent_id' => $this->agent->id, 'statut' => 'VALIDE',
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 6, 'montant_total' => 270000, 'avance' => 189000, 'reliquat' => 81000,
        'avance_engagee' => 189000,
    ]);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'AVANCE', 'montant' => 189000, 'operateur' => 'MTN', 'statut' => 'CONFIRME']);
    $this->reliquat = Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'RELIQUAT', 'montant' => 81000, 'operateur' => 'MTN', 'statut' => 'EN_ATTENTE']);
});

/** Force le reliquat en trop-perçu via un amendement fortement réducteur. */
function amenderEnTropPercu(object $t): void
{
    $t->actingAs($t->gestionnaire)
        ->postJson("/api/ordres/{$t->om->id}/amender", ['nb_jours' => 3, 'motif' => 'Forte réduction de la durée de mission.'])
        ->assertOk();
}

it('bascule le versement de reliquat en A_RECOUVRER après amendement en trop-perçu', function () {
    amenderEnTropPercu($this);

    $v = $this->reliquat->fresh();
    expect($v->montant)->toBe(-54000)
        ->and($v->statut)->toBe('A_RECOUVRER');
});

it('liste les régularisations avec le montant à recouvrer en valeur absolue', function () {
    amenderEnTropPercu($this);

    $reponse = $this->actingAs($this->finance)->getJson('/api/paiements/regularisations')->assertOk();

    $reponse->assertJsonPath('data.0.versement_id', $this->reliquat->id)
        // Expose l'OM concerné : l'UI relie la régularisation à la fiche de l'OM (pour l'amender).
        ->assertJsonPath('data.0.ordre_mission_id', $this->om->id)
        ->assertJsonPath('data.0.montant_a_recouvrer', 54000)
        ->assertJsonPath('data.0.statut', 'A_RECOUVRER');
});

it('filtre les régularisations par statut', function () {
    amenderEnTropPercu($this);

    $this->actingAs($this->finance)->getJson('/api/paiements/regularisations?statut=RECOUVRE')
        ->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($this->finance)->getJson('/api/paiements/regularisations?statut=A_RECOUVRER')
        ->assertOk()->assertJsonCount(1, 'data');
});

/** Crée une régularisation (versement A_RECOUVRER) autonome, réf. SEMO au choix. */
function creerRegularisation(string $reference, int $montantARecouvrer): void
{
    $mission = Mission::factory()->create(['zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false]);
    $agent = Agent::factory()->create(['operateur' => 'MTN']);
    $om = OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'agent_id' => $agent->id, 'reference_semo' => $reference,
        'statut' => 'VALIDE', 'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'nb_jours' => 1, 'montant_total' => 1,
        'avance' => 1, 'reliquat' => -$montantARecouvrer, 'avance_engagee' => 1,
    ]);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id, 'type' => 'RELIQUAT', 'montant' => -$montantARecouvrer,
        'operateur' => 'MTN', 'statut' => 'A_RECOUVRER',
    ]);
}

it('pagine les régularisations et expose le total global à recouvrer', function () {
    // 6 trop-perçus de 10 000 (le reliquat du contexte reste EN_ATTENTE : hors périmètre).
    for ($i = 1; $i <= 6; $i++) {
        creerRegularisation(sprintf('OM-REG-%03d', $i), 10000);
    }

    $this->actingAs($this->finance)->getJson('/api/paiements/regularisations?per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data')            // page limitée
        ->assertJsonPath('total', 6)            // 6 régularisations au total
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('total_a_recouvrer', 60000); // total GLOBAL, pas seulement la page
});

it('filtre les régularisations par recherche (référence SEMO)', function () {
    creerRegularisation('OM-ZEBRE-999', 5000);
    creerRegularisation('OM-LAMBDA-001', 7000);

    $this->actingAs($this->finance)->getJson('/api/paiements/regularisations?search=ZEBRE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_semo', 'OM-ZEBRE-999');
});

it('marque une régularisation comme recouvrée', function () {
    amenderEnTropPercu($this);

    $this->actingAs($this->finance)
        ->postJson("/api/paiements/regularisations/{$this->reliquat->id}/recouvrer")
        ->assertOk()
        ->assertJsonPath('data.statut', 'RECOUVRE');

    $v = $this->reliquat->fresh();
    expect($v->statut)->toBe('RECOUVRE')
        ->and($v->date_paiement)->not->toBeNull();
});

it('refuse de recouvrer un versement qui n’est pas à recouvrer', function () {
    // Reliquat resté EN_ATTENTE (aucun trop-perçu).
    $this->actingAs($this->finance)
        ->postJson("/api/paiements/regularisations/{$this->reliquat->id}/recouvrer")
        ->assertStatus(422)->assertJsonValidationErrors('versement');
});

it('exclut un versement A_RECOUVRER du périmètre payable', function () {
    amenderEnTropPercu($this);

    // Même remis artificiellement à un montant positif, un A_RECOUVRER reste hors lot.
    Versement::where('id', $this->reliquat->id)->update(['montant' => 40500]);
    expect(Versement::eligiblesLot($this->mission->id, 'RELIQUAT', 'MTN')->count())->toBe(0);
});

it('exige la permission paiements.confirmer pour recouvrer', function () {
    amenderEnTropPercu($this);

    $this->actingAs($this->gestionnaire)
        ->postJson("/api/paiements/regularisations/{$this->reliquat->id}/recouvrer")
        ->assertForbidden();
});

// ── Document de retenue (PDF pour la RH) ──

it('produit un document de retenue PDF pour un trop-perçu', function () {
    amenderEnTropPercu($this);

    $reponse = $this->actingAs($this->finance)
        ->get("/api/paiements/regularisations/{$this->reliquat->id}/document")
        ->assertOk();

    expect($reponse->headers->get('content-type'))->toContain('application/pdf');
});

it('produit encore le document une fois la régularisation recouvrée', function () {
    amenderEnTropPercu($this);
    $this->actingAs($this->finance)->postJson("/api/paiements/regularisations/{$this->reliquat->id}/recouvrer")->assertOk();

    $this->actingAs($this->finance)
        ->get("/api/paiements/regularisations/{$this->reliquat->id}/document")
        ->assertOk();
});

it('refuse le document de retenue pour un versement qui n’est pas une régularisation', function () {
    // Reliquat resté EN_ATTENTE (aucun trop-perçu).
    $this->actingAs($this->finance)
        ->getJson("/api/paiements/regularisations/{$this->reliquat->id}/document")
        ->assertStatus(422)->assertJsonValidationErrors('versement');
});

it('porte le bénéficiaire, le trop-perçu et le motif dans les données du document', function () {
    amenderEnTropPercu($this);

    $data = app(PdfGenerator::class)->donneesRetenue($this->reliquat->fresh());

    expect($data['beneficiaire'])->toContain($this->agent->nom)
        ->and($data['trop_percu'])->toBe('54 000')
        ->and($data['trop_percu_lettres'])->toBe('Cinquante-quatre mille (54 000) FRANCS CFA')
        ->and($data['avance_engagee'])->toBe('189 000')
        ->and($data['statut'])->toBe('A_RECOUVRER')
        ->and($data['a_amendement'])->toBeTrue()
        ->and($data['nb_jours_avant'])->toBe(6)
        ->and($data['nb_jours_apres'])->toBe(3)
        ->and($data['motif'])->toContain('Forte réduction');
});
