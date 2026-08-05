<?php

use App\Models\User;
use App\Models\Versement;
use Carbon\Carbon;

/*
 * Historique des versements confirmés : borne date_fin (date_paiement est un
 * TIMESTAMP → la journée entière doit être couverte) et présence du bénéficiaire
 * dans la réponse (VersementResource).
 */

beforeEach(function () {
    $this->user = User::factory()->agentFinancier()->create();
});

it('inclut un versement confirmé en journée le dernier jour de la plage', function () {
    Versement::factory()->confirme()->create([
        'date_paiement' => Carbon::parse('2026-01-31 14:00:00'),
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/paiements/historique?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('exclut un versement postérieur à la borne de fin', function () {
    Versement::factory()->confirme()->create([
        'date_paiement' => Carbon::parse('2026-02-01 00:01:00'),
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/paiements/historique?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('expose le bénéficiaire du versement dans l’historique', function () {
    $versement = Versement::factory()->confirme()->create(['date_paiement' => now()]);
    $agent = $versement->ordreMission->agent;

    $this->actingAs($this->user)
        ->getJson('/api/paiements/historique')
        ->assertOk()
        ->assertJsonPath('data.0.beneficiaire', trim("{$agent->nom} {$agent->prenoms}"));
});
