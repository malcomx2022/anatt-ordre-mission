<?php

use App\Models\TauxPerDiem;
use App\Models\User;

it('liste les taux actifs', function () {
    $user = User::factory()->admin()->create();
    TauxPerDiem::factory()->count(3)->create(['actif' => true]);
    TauxPerDiem::factory()->create(['actif' => false]);

    $response = $this->actingAs($user)->getJson('/api/taux');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('cree un nouveau taux', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->postJson('/api/taux', [
        'categorie' => 'A1',
        'destination_type' => 'LOCALE',
        'montant_journalier' => 30000,
        'annee' => 2026,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.montant_journalier'))->toBe('30000.00');
});

it('met a jour un taux', function () {
    $user = User::factory()->admin()->create();
    $taux = TauxPerDiem::factory()->create(['montant_journalier' => 25000]);

    $response = $this->actingAs($user)->putJson("/api/taux/{$taux->id}", [
        'montant_journalier' => 35000,
    ]);

    $response->assertOk();
    expect((float) $taux->fresh()->montant_journalier)->toBe(35000.0);
});
