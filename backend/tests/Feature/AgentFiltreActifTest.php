<?php

use App\Models\Agent;
use App\Models\User;

/*
 * Filtre « actif » de la liste des agents : true/false filtrent, mais une valeur
 * VIDE (« Tous ») ne doit PAS être interprétée comme false — sinon la liste ne
 * remonterait que les inactifs.
 */

beforeEach(function () {
    $this->user = User::factory()->gestionnaireOm()->create();
    Agent::factory()->create(['matricule' => 'ACTIF-1']);              // actif = true
    Agent::factory()->inactif()->create(['matricule' => 'INACTIF-1']);
});

it('actif=true ne remonte que les agents actifs', function () {
    $this->actingAs($this->user)->getJson('/api/agents?actif=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.matricule', 'ACTIF-1');
});

it('actif=false ne remonte que les agents inactifs', function () {
    $this->actingAs($this->user)->getJson('/api/agents?actif=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.matricule', 'INACTIF-1');
});

it('actif vide (« Tous ») remonte tous les agents', function () {
    $this->actingAs($this->user)->getJson('/api/agents?actif=')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('sans filtre actif, remonte tous les agents', function () {
    $this->actingAs($this->user)->getJson('/api/agents')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
