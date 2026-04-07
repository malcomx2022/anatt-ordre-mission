<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->agent = Agent::factory()->create(['categorie' => 'A1']);
    $this->taux = TauxPerDiem::factory()->create([
        'categorie' => 'A1',
        'destination_type' => 'LOCALE',
        'montant_journalier' => 25000,
        'annee' => now()->year,
    ]);
});

it('liste les ordres de mission', function () {
    OrdreMission::factory()->count(3)->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->getJson('/api/ordres');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('filtre les missions par statut', function () {
    OrdreMission::factory()->create(['statut' => 'BROUILLON', 'taux_id' => $this->taux->id]);
    OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);
    OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->getJson('/api/ordres?statut=SOUMIS');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('cree un ordre avec calcul automatique per diem', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/ordres', [
        'agent_id' => $this->agent->id,
        'objet' => 'Mission de test',
        'destination' => 'Parakou',
        'date_depart' => '2026-05-10',
        'date_retour' => '2026-05-14',
        'taux_id' => $this->taux->id,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.statut'))->toBe('BROUILLON');
    expect($response->json('data.nb_jours'))->toBe(5);
    expect((float) $response->json('data.montant_per_diem'))->toBe(125000.0);
    expect($response->json('data.numero'))->toStartWith('OM-');
});

it('refuse modification si ordre non brouillon', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->putJson("/api/ordres/{$ordre->id}", [
        'objet' => 'Modifie',
    ]);

    $response->assertStatus(422);
});

it('refuse suppression si ordre non brouillon', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->deleteJson("/api/ordres/{$ordre->id}");

    $response->assertStatus(422);
});

it('soumet un brouillon vers soumis', function () {
    $ordre = OrdreMission::factory()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->postJson("/api/ordres/{$ordre->id}/soumettre");

    $response->assertOk();
    expect($response->json('data.statut'))->toBe('SOUMIS');
});

it('valide N1 avec chef de service', function () {
    $chef = User::factory()->chefService()->create();
    Agent::factory()->create(['user_id' => $chef->id, 'service' => $this->agent->service]);

    $ordre = OrdreMission::factory()->soumis()->create([
        'agent_id' => $this->agent->id,
        'taux_id' => $this->taux->id,
    ]);

    $response = $this->actingAs($chef)->postJson("/api/ordres/{$ordre->id}/valider-n1");

    $response->assertOk();
    expect($response->json('data.statut'))->toBe('VALIDE_N1');
});

it('refuse validation N1 par un agent', function () {
    $agentUser = User::factory()->create(['role' => 'AGENT']);
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($agentUser)->postJson("/api/ordres/{$ordre->id}/valider-n1");

    $response->assertStatus(422);
});

it('approuve DG avec directeur', function () {
    $dg = User::factory()->directeur()->create();
    $ordre = OrdreMission::factory()->valideN1()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($dg)->postJson("/api/ordres/{$ordre->id}/approuver");

    $response->assertOk();
    expect($response->json('data.statut'))->toBe('APPROUVE_DG');
});

it('refuse approbation DG par chef service', function () {
    $chef = User::factory()->chefService()->create();
    $ordre = OrdreMission::factory()->valideN1()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($chef)->postJson("/api/ordres/{$ordre->id}/approuver");

    $response->assertStatus(422);
});

it('rejete un ordre avec commentaire obligatoire', function () {
    $response = $this->actingAs($this->admin)->postJson("/api/ordres/" . OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id])->id . "/rejeter", [
        'commentaire' => 'Ce motif est suffisamment long pour la validation',
    ]);

    $response->assertOk();
    expect($response->json('data.statut'))->toBe('REJETE');
});

it('refuse rejet sans commentaire', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->postJson("/api/ordres/{$ordre->id}/rejeter", []);

    $response->assertStatus(422);
});

it('refuse rejet avec commentaire trop court', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $response = $this->actingAs($this->admin)->postJson("/api/ordres/{$ordre->id}/rejeter", [
        'commentaire' => 'Court',
    ]);

    $response->assertStatus(422);
});

it('respecte le workflow complet BROUILLON -> SOUMIS -> VALIDE_N1 -> APPROUVE_DG', function () {
    $chef = User::factory()->chefService()->create();
    Agent::factory()->create(['user_id' => $chef->id, 'service' => $this->agent->service]);
    $dg = User::factory()->directeur()->create();

    $ordre = OrdreMission::factory()->create([
        'agent_id' => $this->agent->id,
        'taux_id' => $this->taux->id,
    ]);

    // BROUILLON -> SOUMIS
    $this->actingAs($this->admin)->postJson("/api/ordres/{$ordre->id}/soumettre")->assertOk();
    expect($ordre->fresh()->statut)->toBe('SOUMIS');

    // SOUMIS -> VALIDE_N1
    $this->actingAs($chef)->postJson("/api/ordres/{$ordre->id}/valider-n1")->assertOk();
    expect($ordre->fresh()->statut)->toBe('VALIDE_N1');

    // VALIDE_N1 -> APPROUVE_DG
    $this->actingAs($dg)->postJson("/api/ordres/{$ordre->id}/approuver")->assertOk();
    expect($ordre->fresh()->statut)->toBe('APPROUVE_DG');
});
