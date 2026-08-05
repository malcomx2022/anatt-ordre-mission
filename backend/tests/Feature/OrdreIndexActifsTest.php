<?php

use App\Models\OrdreMission;
use App\Models\User;

/*
 * Filtre « actifs » de la liste des OM : exclut les statuts terminaux non
 * retenus (REJETE / ANNULE), pour un compteur de volume utile (KPI dashboard).
 */

beforeEach(function () {
    $this->user = User::factory()->lecteur()->create();
});

it('actifs=true exclut les OM rejetés et annulés', function () {
    OrdreMission::factory()->create(['statut' => 'IMPORTE']);
    OrdreMission::factory()->create(['statut' => 'VALIDE']);
    OrdreMission::factory()->create(['statut' => 'REJETE']);
    OrdreMission::factory()->create(['statut' => 'ANNULE']);

    $this->actingAs($this->user)->getJson('/api/ordres?actifs=true')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('sans le filtre actifs, remonte tous les statuts', function () {
    OrdreMission::factory()->create(['statut' => 'IMPORTE']);
    OrdreMission::factory()->create(['statut' => 'REJETE']);

    $this->actingAs($this->user)->getJson('/api/ordres')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
