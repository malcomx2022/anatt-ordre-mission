<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\User;

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
