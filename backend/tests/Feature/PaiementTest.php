<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\Paiement;
use App\Models\TauxPerDiem;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->taux = TauxPerDiem::factory()->create(['montant_journalier' => 25000, 'annee' => now()->year]);
});

it('liste les paiements', function () {
    Paiement::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/paiements');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('filtre les paiements par operateur', function () {
    Paiement::factory()->create(['operateur' => 'MTN']);
    Paiement::factory()->create(['operateur' => 'MOOV']);
    Paiement::factory()->create(['operateur' => 'MTN']);

    $response = $this->actingAs($this->admin)->getJson('/api/paiements?operateur=MTN');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('genere un fichier paiement Moov', function () {
    $agent = Agent::factory()->create(['numero_moov' => '22997123456']);
    $ordre = OrdreMission::factory()->approuveDG()->create([
        'agent_id' => $agent->id,
        'taux_id' => $this->taux->id,
        'montant_per_diem' => 75000,
    ]);

    $response = $this->actingAs($this->admin)->postJson('/api/paiements/generer/moov', [
        'ordre_ids' => [$ordre->id],
    ]);

    $response->assertOk();
    expect($response->json('data.nb_paiements'))->toBe(1);
    expect($response->json('data.fichier'))->toContain('MOOV_');

    $this->assertDatabaseHas('paiements', [
        'ordre_mission_id' => $ordre->id,
        'operateur' => 'MOOV',
        'montant' => 75000,
        'statut' => 'EN_ATTENTE',
    ]);
});

it('genere un fichier paiement MTN', function () {
    $agent = Agent::factory()->create(['numero_mtn' => '22961234567']);
    $ordre = OrdreMission::factory()->approuveDG()->create([
        'agent_id' => $agent->id,
        'taux_id' => $this->taux->id,
        'montant_per_diem' => 50000,
    ]);

    $response = $this->actingAs($this->admin)->postJson('/api/paiements/generer/mtn', [
        'ordre_ids' => [$ordre->id],
    ]);

    $response->assertOk();
    expect($response->json('data.nb_paiements'))->toBe(1);
    expect($response->json('data.fichier'))->toContain('MTN_');
});

it('ignore les agents sans numero Moov', function () {
    $agent = Agent::factory()->create(['numero_moov' => null]);
    $ordre = OrdreMission::factory()->approuveDG()->create([
        'agent_id' => $agent->id,
        'taux_id' => $this->taux->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson('/api/paiements/generer/moov', [
        'ordre_ids' => [$ordre->id],
    ]);

    $response->assertOk();
    expect($response->json('data.nb_paiements'))->toBe(0);
});

it('retourne les statuts agreges', function () {
    Paiement::factory()->succes()->count(3)->create(['operateur' => 'MTN']);
    Paiement::factory()->echec()->count(1)->create(['operateur' => 'MOOV']);

    $response = $this->actingAs($this->admin)->getJson('/api/paiements/statuts');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('refuse acces non authentifie aux paiements', function () {
    $response = $this->getJson('/api/paiements');

    $response->assertStatus(401);
});
