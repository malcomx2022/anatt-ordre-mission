<?php

use App\Models\Agent;
use App\Models\User;

/*
 * Compteurs GLOBAUX de l'écran Personnel (/api/agents/stats) : total, actifs,
 * inactifs, répartition MTN/MOOV — sur tout le jeu FILTRÉ, pas seulement la page.
 */

beforeEach(function () {
    $this->lecteur = User::factory()->gestionnaireOm()->create();
    Agent::factory()->mtn()->count(3)->create();
    Agent::factory()->moov()->count(2)->create();
    Agent::factory()->mtn()->inactif()->create();
});

it('renvoie des compteurs globaux cohérents', function () {
    $this->actingAs($this->lecteur)->getJson('/api/agents/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 6)
        ->assertJsonPath('data.actifs', 5)
        ->assertJsonPath('data.inactifs', 1)
        ->assertJsonPath('data.mtn', 4)   // 3 actifs + 1 inactif
        ->assertJsonPath('data.moov', 2);
});

it('respecte les filtres (ex. opérateur)', function () {
    $this->actingAs($this->lecteur)->getJson('/api/agents/stats?operateur=MOOV')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.moov', 2)
        ->assertJsonPath('data.mtn', 0);
});

it('exige la permission de consulter le personnel', function () {
    $this->getJson('/api/agents/stats')->assertUnauthorized();
});
