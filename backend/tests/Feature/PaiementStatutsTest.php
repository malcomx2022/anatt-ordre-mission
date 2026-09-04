<?php

use App\Models\User;
use App\Models\Versement;

/*
 * Synthèse « Statuts des versements » (/api/paiements/statuts) : elle porte sur
 * le circuit Mobile Money et EXCLUT les régularisations (A_RECOUVRER / RECOUVRE),
 * qui ont des montants négatifs et fausseraient le total.
 */

beforeEach(function () {
    $this->user = User::factory()->agentFinancier()->create();
});

it('exclut les régularisations de la synthèse des statuts', function () {
    Versement::factory()->confirme()->create(['montant' => 200000, 'operateur' => 'MTN']);
    Versement::factory()->create(['statut' => 'EN_ATTENTE', 'montant' => 50000, 'operateur' => 'MOOV']);
    // Trop-perçus : ne doivent PAS apparaître.
    Versement::factory()->create(['statut' => 'A_RECOUVRER', 'montant' => -30000, 'operateur' => 'MTN']);
    Versement::factory()->create(['statut' => 'RECOUVRE', 'montant' => -10000, 'operateur' => 'MOOV']);

    $data = $this->actingAs($this->user)->getJson('/api/paiements/statuts')
        ->assertOk()
        ->json('data');

    $statuts = collect($data)->pluck('statut')->unique()->values()->all();

    expect($statuts)->toContain('CONFIRME')
        ->and($statuts)->toContain('EN_ATTENTE')
        ->and($statuts)->not->toContain('A_RECOUVRER')
        ->and($statuts)->not->toContain('RECOUVRE');
});
