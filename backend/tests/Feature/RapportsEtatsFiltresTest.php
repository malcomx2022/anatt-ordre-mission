<?php

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * Revue « justesse & filtres » des Rapports et des États de paiement.
 * Chaque filtre est exercé et son résultat vérifié (complète RapportDateBorneTest,
 * RapportImputationTest, RapportTropPercusTest, EtatPaiementTest, MissionsIndexTest).
 */

beforeEach(function () {
    // LECTEUR : porte à la fois om.consulter (états/missions) et rapports.consulter.
    $this->lecteur = User::factory()->lecteur()->create();
});

/** Versement CONFIRMÉ (seul périmètre des rapports), payé à une date donnée. */
function paiementConfirme(string $datePaiement, int $montant, string $operateur = 'MTN', string $zone = 'INTERIEUR', string $imputation = 'IMP-100'): void
{
    $mission = Mission::factory()->create(['zone_code' => $zone, 'imputation_budgetaire' => $imputation]);
    $agent = Agent::factory()->create(['operateur' => $operateur]);
    $om = OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'agent_id' => $agent->id, 'statut' => 'SOLDE', 'zone_code' => $zone,
    ]);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id, 'type' => 'AVANCE', 'montant' => $montant,
        'operateur' => $operateur, 'statut' => 'CONFIRME', 'date_paiement' => $datePaiement,
    ]);
}

// ── Rapport mensuel : filtre `mois` ──

it('mensuel : ne compte que les paiements confirmés du mois demandé', function () {
    paiementConfirme('2026-01-15 10:00:00', 100000);
    paiementConfirme('2026-02-05 10:00:00', 40000);

    $this->actingAs($this->lecteur)->getJson('/api/rapports/mensuel?mois=2026-01')
        ->assertOk()
        ->assertJsonPath('data.synthese.nb_versements', 1)
        ->assertJsonPath('data.synthese.montant_total', 100000);
});

// ── Rapports par dimension : agrégats + filtre de dates ──

it('agrège par opérateur et par zone sur le périmètre confirmé', function () {
    paiementConfirme('2026-01-10 09:00:00', 100000, 'MTN', 'INTERIEUR');
    paiementConfirme('2026-01-11 09:00:00', 40000, 'MOOV', 'EXT_ZONE_I');

    $this->actingAs($this->lecteur)->getJson('/api/rapports/operateurs')
        ->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($this->lecteur)->getJson('/api/rapports/zones')
        ->assertOk()->assertJsonCount(2, 'data');
});

it('le filtre de dates (date_debut/date_fin) exclut les paiements hors plage', function () {
    paiementConfirme('2026-01-10 09:00:00', 100000);
    paiementConfirme('2026-03-10 09:00:00', 50000);

    $this->actingAs($this->lecteur)
        ->getJson('/api/rapports/agents?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()->assertJsonCount(1, 'data');
});

// ── Export : filtre `dimension` ──

it('export : chaque dimension produit son état', function () {
    paiementConfirme('2026-01-10 09:00:00', 100000, 'MTN', 'INTERIEUR');

    foreach (['agent', 'operateur', 'zone', 'imputation'] as $dim) {
        $this->actingAs($this->lecteur)->getJson("/api/rapports/export?dimension={$dim}&format=xlsx")
            ->assertOk()
            ->assertJsonPath('data.dimension', $dim)
            ->assertJsonPath('data.format', 'xlsx')
            ->assertJsonPath('data.nb_lignes', 1);
    }
});

// ── États de paiement : filtre `tranche` ──

it('états : le filtre tranche ne renvoie que la tranche demandée', function () {
    $mission = Mission::factory()->create();
    foreach (['AVANCE', 'RELIQUAT'] as $t) {
        EtatPaiement::create([
            'mission_id' => $mission->id, 'tranche' => $t, 'statut' => 'BROUILLON',
            'nb_beneficiaires' => 1, 'montant_total' => 1000,
            'montant_avance' => $t === 'AVANCE' ? 1000 : 0,
            'montant_reliquat' => $t === 'RELIQUAT' ? 1000 : 0,
        ]);
    }

    $this->actingAs($this->lecteur)->getJson('/api/etats')->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($this->lecteur)->getJson('/api/etats?tranche=RELIQUAT')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.tranche', 'RELIQUAT');
    $this->actingAs($this->lecteur)->getJson('/api/etats?tranche=AVANCE')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.tranche', 'AVANCE');
});

// ── États de paiement : filtre `statut` combiné à `tranche` ──

it('états : combine les filtres statut et tranche', function () {
    $mission = Mission::factory()->create();
    // Deux états de tranches distinctes, statuts distincts.
    EtatPaiement::create(['mission_id' => $mission->id, 'tranche' => 'AVANCE', 'statut' => 'VALIDE',
        'nb_beneficiaires' => 1, 'montant_total' => 1000, 'montant_avance' => 1000, 'montant_reliquat' => 0]);
    EtatPaiement::create(['mission_id' => $mission->id, 'tranche' => 'RELIQUAT', 'statut' => 'BROUILLON',
        'nb_beneficiaires' => 1, 'montant_total' => 500, 'montant_avance' => 0, 'montant_reliquat' => 500]);

    $this->actingAs($this->lecteur)->getJson('/api/etats?statut=BROUILLON&tranche=RELIQUAT')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.statut', 'BROUILLON');
    $this->actingAs($this->lecteur)->getJson('/api/etats?statut=BROUILLON&tranche=AVANCE')
        ->assertOk()->assertJsonCount(0, 'data');
});
