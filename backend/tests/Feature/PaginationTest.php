<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->taux = TauxPerDiem::factory()->create(['montant_journalier' => 25000, 'annee' => now()->year]);
});

// ── Structure de la reponse paginee ──

it('retourne la structure de pagination standard', function () {
    Agent::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

// ── per_page par defaut ──

it('utilise per_page=15 par defaut pour les agents', function () {
    Agent::factory()->count(20)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(15);
    expect($response->json('meta.per_page'))->toBe(15);
});

it('utilise per_page=15 par defaut pour les ordres', function () {
    OrdreMission::factory()->count(20)->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->getJson('/api/ordres');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(15);
});

// ── per_page personnalise ──

it('accepte un per_page personnalise', function () {
    Agent::factory()->count(20)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.per_page'))->toBe(5);
});

// ── Limite max ──

it('plafonne per_page a 100 maximum', function () {
    Agent::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=999');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(100);
});

// ── Limite min ──

it('force per_page minimum a 5', function () {
    Agent::factory()->count(10)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=1');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(5);
});

it('gere per_page negatif', function () {
    Agent::factory()->count(10)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/agents?per_page=-10');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(5);
});

// ── Navigation entre pages ──

it('navigue entre les pages', function () {
    Agent::factory()->count(12)->create();

    $page1 = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5&page=1');
    $page2 = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5&page=2');
    $page3 = $this->actingAs($this->admin)->getJson('/api/agents?per_page=5&page=3');

    expect($page1->json('data'))->toHaveCount(5);
    expect($page2->json('data'))->toHaveCount(5);
    expect($page3->json('data'))->toHaveCount(2);
    expect($page1->json('meta.current_page'))->toBe(1);
    expect($page2->json('meta.current_page'))->toBe(2);
    expect($page1->json('meta.last_page'))->toBe(3);
    expect($page1->json('meta.total'))->toBe(12);
});

// ── Pagination sur les missions ──

it('pagine les ordres de mission', function () {
    OrdreMission::factory()->count(8)->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->getJson('/api/ordres?per_page=5');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.total'))->toBe(8);
    expect($response->json('meta.last_page'))->toBe(2);
});

// ── Pagination sur les paiements ──

it('pagine les paiements', function () {
    \App\Models\Paiement::factory()->count(8)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/paiements?per_page=5');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.total'))->toBe(8);
});
