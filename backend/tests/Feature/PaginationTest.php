<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('retourne la structure de pagination standard', function () {
    Agent::factory()->count(3)->create();

    $this->actingAs($this->admin)->getJson('/api/agents')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('utilise per_page=15 par defaut pour les agents', function () {
    Agent::factory()->count(20)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents');
    expect($response->json('data'))->toHaveCount(15);
    expect($response->json('meta.per_page'))->toBe(15);
});

it('utilise per_page=15 par defaut pour les ordres', function () {
    OrdreMission::factory()->count(20)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/ordres');
    expect($response->json('data'))->toHaveCount(15);
});

it('accepte un per_page personnalise', function () {
    Agent::factory()->count(20)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5');
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.per_page'))->toBe(5);
});

it('plafonne per_page a 100 maximum', function () {
    Agent::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=999');
    expect($response->json('meta.per_page'))->toBe(100);
});

it('force per_page minimum a 5', function () {
    Agent::factory()->count(10)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=1');
    expect($response->json('meta.per_page'))->toBe(5);
});

it('navigue entre les pages', function () {
    Agent::factory()->count(12)->create();

    $page1 = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5&page=1');
    $page3 = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5&page=3');

    expect($page1->json('data'))->toHaveCount(5);
    expect($page3->json('data'))->toHaveCount(2);
    expect($page1->json('meta.last_page'))->toBe(3);
    expect($page1->json('meta.total'))->toBe(12);
});

it('pagine les ordres de mission', function () {
    OrdreMission::factory()->count(8)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/ordres?per_page=5');
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.total'))->toBe(8);
    expect($response->json('meta.last_page'))->toBe(2);
});

it('plafonne aussi per_page sur les missions et les états de paiement', function () {
    $controleur = User::factory()->controleur()->create();

    expect($this->actingAs($controleur)->getJson('/api/missions?per_page=100000')->json('meta.per_page'))->toBe(100)
        ->and($this->actingAs($controleur)->getJson('/api/etats?per_page=100000')->json('meta.per_page'))->toBe(100)
        // Le défaut de ces deux écrans reste 20.
        ->and($this->actingAs($controleur)->getJson('/api/missions')->json('meta.per_page'))->toBe(20);
});

it('filtre les ordres sur l’opérateur du versement, pas celui de l’agent', function () {
    // Agent passé de MOOV à MTN APRÈS génération : son versement reste MOOV.
    $agent = Agent::factory()->mtn()->create();
    $om = OrdreMission::factory()->create(['agent_id' => $agent->id]);
    Versement::factory()->create(['ordre_mission_id' => $om->id, 'operateur' => 'MOOV']);

    $ids = fn (string $op) => collect($this->actingAs($this->admin)->getJson("/api/ordres?operateur={$op}")->json('data'))->pluck('id');

    expect($ids('MOOV'))->toContain($om->id)
        ->and($ids('MTN'))->not->toContain($om->id);
});
