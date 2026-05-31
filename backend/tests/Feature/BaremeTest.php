<?php

use App\Models\Bareme;
use App\Models\User;
use Database\Seeders\BaremeSeeder;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('liste le barème (lecture pour tout authentifié)', function () {
    $this->seed(BaremeSeeder::class);
    $lecteur = User::factory()->lecteur()->create();

    $this->actingAs($lecteur)->getJson('/api/baremes')
        ->assertOk()
        ->assertJsonCount(45, 'data');
});

it('crée une ligne de barème', function () {
    $this->actingAs($this->admin)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ])->assertStatus(201)->assertJsonPath('data.taux_journalier', 45000);
});

it('refuse un triplet en doublon', function () {
    Bareme::factory()->create(['categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE']);

    $this->actingAs($this->admin)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 99999,
    ])->assertStatus(422);
});

it('refuse un type incompatible avec la zone', function () {
    // UN_REPAS (repas) interdit en zone extérieure.
    $this->actingAs($this->admin)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_1', 'zone_code' => 'EXT_ZONE_I', 'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 10000,
    ])->assertStatus(422)->assertJsonValidationErrors(['type_prise_en_charge']);
});

it('met à jour le taux journalier', function () {
    $bareme = Bareme::factory()->create(['taux_journalier' => 45000]);

    $this->actingAs($this->admin)->putJson("/api/baremes/{$bareme->id}", ['taux_journalier' => 50000])
        ->assertOk()
        ->assertJsonPath('data.taux_journalier', 50000);
});

it('supprime une ligne de barème', function () {
    $bareme = Bareme::factory()->create();

    $this->actingAs($this->admin)->deleteJson("/api/baremes/{$bareme->id}")->assertOk();

    expect(Bareme::find($bareme->id))->toBeNull();
});
