<?php

use App\Models\Agent;
use App\Models\LotPaiement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use Database\Seeders\BaremeSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;

/**
 * Recette de bout en bout (prompt 6.2) :
 * OM réel réf. SEMO 0541405636, mission BOHICON (INTERIEUR, JOURNEE_COMPLETE),
 * un participant INDICE_SUP_800 + un CAV INDICE_INF_400 ; calcul per diem, versements
 * AVANCE/RELIQUAT, génération MTN ET Moov, confirmation, statuts, état mensuel.
 */
beforeEach(function () {
    // Recette : nombreux appels paiement -> on neutralise le rate limiting.
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    $this->seed(BaremeSeeder::class);
    $this->admin = User::factory()->superAdmin()->create(); // SUPER_ADMIN : bypass de toutes les gardes (Gate::before)
    $this->dada = Agent::factory()->mtn()->categorie('INDICE_SUP_800')->create(['nom' => 'DADA', 'prenoms' => 'Richard']);
    $this->cav = Agent::factory()->moov()->categorie('INDICE_INF_400')->create(['nom' => 'AKPACA', 'prenoms' => 'Gérard']);
});

it('déroule le scénario complet SEMO 0541405636 jusqu’à l’état mensuel', function () {
    // ── 1. Retranscription : 1 mission -> 2 OM (participant + CAV) ──
    $this->actingAs($this->admin)->postJson('/api/missions', [
        'objet' => 'Supervision des opérations de contrôle technique',
        'destination' => 'Bohicon',
        'zone_code' => 'INTERIEUR',
        'date_depart' => '2025-11-12',
        'date_retour' => '2025-11-14',   // 3 jours inclusifs
        'prise_en_charge_tiers' => false,
        'ordres' => [
            ['reference_semo' => '0541405636', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
            ['reference_semo' => '0541405637', 'agent_id' => $this->cav->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
        ],
    ])->assertStatus(201);

    $omDada = OrdreMission::where('reference_semo', '0541405636')->first();
    $omCav = OrdreMission::where('reference_semo', '0541405637')->first();
    $missionId = $omDada->mission_id;

    // Per diem INDICE_SUP_800 / INTERIEUR / JOURNEE_COMPLETE = 45000 × 3 jours.
    expect($omDada->montant_total)->toBe(135000)        // 45000 × 3
        ->and($omDada->avance)->toBe(94500)             // 70 %
        ->and($omDada->reliquat)->toBe(40500);          // 30 %
    // CAV payé selon SA catégorie (INDICE_INF_400 = 30000 × 3).
    expect($omCav->montant_total)->toBe(90000);

    // Versements AVANCE + RELIQUAT par OM.
    expect(Versement::count())->toBe(4);

    // ── 1bis. Validation des OM (prérequis au paiement) : IMPORTE -> VALIDE ──
    foreach ([$omDada, $omCav] as $om) {
        $this->actingAs($this->admin)->postJson("/api/ordres/{$om->id}/valider")
            ->assertOk();
        expect($om->fresh()->statut)->toBe('VALIDE');
    }

    // ── 2. AVANCE : MTN (DADA) + Moov (CAV), génération puis confirmation ──
    foreach (['MTN', 'MOOV'] as $op) {
        $this->actingAs($this->admin)->postJson('/api/paiements/lots/generer', [
            'mission_id' => $missionId, 'tranche' => 'AVANCE', 'operateur' => $op,
        ])->assertOk()->assertHeader('content-disposition');

        $lot = LotPaiement::where(['mission_id' => $missionId, 'tranche' => 'AVANCE', 'operateur' => $op])->first();
        expect($lot->statut)->toBe('GENERE');

        $this->actingAs($this->admin)->postJson("/api/paiements/lots/{$lot->id}/confirmer")
            ->assertOk()->assertJsonPath('data.statut', 'CONFIRME');
    }

    // OM pas encore SOLDE (reliquat à venir).
    expect($omDada->fresh()->statut)->not->toBe('SOLDE');

    // ── 3. RELIQUAT : MTN + Moov, génération puis confirmation ──
    foreach (['MTN', 'MOOV'] as $op) {
        $this->actingAs($this->admin)->postJson('/api/paiements/lots/generer', [
            'mission_id' => $missionId, 'tranche' => 'RELIQUAT', 'operateur' => $op,
        ])->assertOk();

        $lot = LotPaiement::where(['mission_id' => $missionId, 'tranche' => 'RELIQUAT', 'operateur' => $op])->first();
        $this->actingAs($this->admin)->postJson("/api/paiements/lots/{$lot->id}/confirmer")->assertOk();
    }

    // ── 4. Les deux OM sont SOLDÉS ──
    expect($omDada->fresh()->statut)->toBe('SOLDE')
        ->and($omCav->fresh()->statut)->toBe('SOLDE');
    expect(Versement::where('statut', 'CONFIRME')->count())->toBe(4);

    // ── 5. État mensuel (alimenté par les versements confirmés) ──
    $mois = now()->format('Y-m');
    $this->actingAs($this->admin)->getJson("/api/rapports/mensuel?mois={$mois}")
        ->assertOk()
        ->assertJsonPath('data.synthese.nb_versements', 4)
        ->assertJsonPath('data.synthese.montant_total', 225000); // 135000 (DADA) + 90000 (CAV)
});
