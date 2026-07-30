<?php

use App\Models\Agent;
use App\Models\User;

beforeEach(function () {
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

it('crée un agent et déduit la catégorie depuis l’indice', function () {
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'DADA', 'prenoms' => 'Richard', 'matricule' => 'ANT-1001',
        'telephone' => '0190000001', 'operateur' => 'MTN', 'indice' => 850,
    ])->assertStatus(201)
      ->assertJsonPath('data.categorie', 'ORDRE_1');

    expect(Agent::where('matricule', 'ANT-1001')->value('categorie'))->toBe('ORDRE_1');
});

it('déduit ORDRE_3 depuis le salaire brut', function () {
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'AKPACA', 'prenoms' => 'Gérard', 'matricule' => 'ANT-1002',
        'telephone' => '0140000002', 'operateur' => 'MOOV', 'salaire_brut' => 150000,
    ])->assertStatus(201)
      ->assertJsonPath('data.categorie', 'ORDRE_3');
});

it('refuse un téléphone au mauvais format', function () {
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'X', 'prenoms' => 'Y', 'matricule' => 'ANT-9',
        'telephone' => '12345', 'operateur' => 'MTN', 'indice' => 500,
    ])->assertStatus(422)->assertJsonValidationErrors(['telephone']);
});

it('refuse une incohérence numéro / opérateur', function () {
    // 014X -> Moov, mais opérateur déclaré MTN.
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'X', 'prenoms' => 'Y', 'matricule' => 'ANT-10',
        'telephone' => '0140000000', 'operateur' => 'MTN', 'indice' => 500,
    ])->assertStatus(422)->assertJsonValidationErrors(['telephone']);
});

it('exige l’indice ou le salaire brut', function () {
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'X', 'prenoms' => 'Y', 'matricule' => 'ANT-11',
        'telephone' => '0190000000', 'operateur' => 'MTN',
    ])->assertStatus(422);
});

it('recherche un agent par nom pour l’autocomplétion', function () {
    Agent::factory()->create(['nom' => 'KOUASSI', 'prenoms' => 'Jean', 'matricule' => 'ANT-K1']);

    $this->actingAs($this->gestion)->getJson('/api/agents/recherche?q=KOUASSI')
        ->assertOk()
        ->assertJsonPath('data.0.nom', 'KOUASSI');
});

it('met à jour un agent', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($this->gestion)->putJson("/api/agents/{$agent->id}", ['grade' => 'Directeur'])
        ->assertOk()
        ->assertJsonPath('data.grade', 'Directeur');
});

it('désactive un agent', function () {
    $agent = Agent::factory()->create(['actif' => true]);

    $this->actingAs($this->gestion)->deleteJson("/api/agents/{$agent->id}")->assertOk();

    expect(Agent::find($agent->id)->actif)->toBeFalse();
});

it('filtre les agents par catégorie', function () {
    Agent::factory()->categorie('ORDRE_1')->create();
    Agent::factory()->categorie('ORDRE_3')->create();

    $this->actingAs($this->gestion)->getJson('/api/agents?categorie=ORDRE_1')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
