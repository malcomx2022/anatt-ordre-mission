<?php

use App\Models\Agent;
use App\Models\User;

it('liste les agents avec pagination', function () {
    $user = User::factory()->admin()->create();
    Agent::factory()->count(5)->create();

    $response = $this->actingAs($user)->getJson('/api/agents');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta']);
    expect($response->json('data'))->toHaveCount(5);
});

it('filtre les agents par recherche', function () {
    $user = User::factory()->admin()->create();
    Agent::factory()->create(['nom' => 'KOUASSI', 'prenom' => 'Jean']);
    Agent::factory()->create(['nom' => 'ADJOVI', 'prenom' => 'Marie']);

    $response = $this->actingAs($user)->getJson('/api/agents?search=KOUASSI');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.nom'))->toBe('KOUASSI');
});

it('cree un agent avec donnees valides', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->postJson('/api/agents', [
        'matricule' => 'ANT-999',
        'nom' => 'TOSSOU',
        'prenom' => 'Marc',
        'email' => 'marc@anatt.bj',
        'telephone' => '22996789012',
        'service' => 'Direction Technique',
        'categorie' => 'B',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.matricule', 'ANT-999')
        ->assertJsonPath('data.nom', 'TOSSOU');
});

it('refuse la creation agent avec matricule duplique', function () {
    $user = User::factory()->admin()->create();
    Agent::factory()->create(['matricule' => 'ANT-001']);

    $response = $this->actingAs($user)->postJson('/api/agents', [
        'matricule' => 'ANT-001',
        'nom' => 'TEST',
        'prenom' => 'Test',
        'email' => 'test@anatt.bj',
        'telephone' => '22900000000',
        'service' => 'Test',
        'categorie' => 'C',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('matricule');
});

it('affiche un agent avec ses missions', function () {
    $user = User::factory()->admin()->create();
    $agent = Agent::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/agents/{$agent->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $agent->id);
});

it('met a jour un agent', function () {
    $user = User::factory()->admin()->create();
    $agent = Agent::factory()->create();

    $response = $this->actingAs($user)->putJson("/api/agents/{$agent->id}", [
        'nom' => 'NOUVEAU_NOM',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.nom', 'NOUVEAU_NOM');
});

it('desactive un agent au lieu de le supprimer', function () {
    $user = User::factory()->admin()->create();
    $agent = Agent::factory()->create(['actif' => true]);

    $response = $this->actingAs($user)->deleteJson("/api/agents/{$agent->id}");

    $response->assertOk();
    expect($agent->fresh()->actif)->toBeFalse();
});

it('refuse acces non authentifie aux agents', function () {
    $response = $this->getJson('/api/agents');

    $response->assertStatus(401);
});
