<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;

beforeEach(function () {
    $this->taux = TauxPerDiem::factory()->create(['montant_journalier' => 25000, 'annee' => now()->year]);
});

// ── ADMIN a toujours acces ──

it('admin accede a toutes les routes protegees', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/rapports/mensuel')->assertOk();
    $this->actingAs($admin)->getJson('/api/audit')->assertOk();
    $this->actingAs($admin)->postJson('/api/taux', [
        'categorie' => 'D', 'destination_type' => 'LOCALE', 'montant_journalier' => 10000, 'annee' => 2026,
    ])->assertStatus(201);
});

// ── Agents CRUD ──

it('drh peut creer un agent', function () {
    $drh = User::factory()->drh()->create();

    $this->actingAs($drh)->postJson('/api/agents', [
        'matricule' => 'ANT-TEST', 'nom' => 'Test', 'prenom' => 'User',
        'email' => 'test@anatt.bj', 'telephone' => '22900000000',
        'service' => 'Test', 'categorie' => 'C',
    ])->assertStatus(201);
});

it('agent ne peut pas creer un agent', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);

    $this->actingAs($agent)->postJson('/api/agents', [
        'matricule' => 'ANT-TEST', 'nom' => 'Test', 'prenom' => 'User',
        'email' => 'test@anatt.bj', 'telephone' => '22900000000',
        'service' => 'Test', 'categorie' => 'C',
    ])->assertStatus(403);
});

it('agent peut lire la liste des agents', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);

    $this->actingAs($agent)->getJson('/api/agents')->assertOk();
});

// ── Validation workflow ──

it('chef service peut valider N1', function () {
    $chef = User::factory()->chefService()->create();
    $agentModel = Agent::factory()->create(['service' => 'Direction Technique']);
    Agent::factory()->create(['user_id' => $chef->id, 'service' => 'Direction Technique']);
    $ordre = OrdreMission::factory()->soumis()->create(['agent_id' => $agentModel->id, 'taux_id' => $this->taux->id]);

    $this->actingAs($chef)->postJson("/api/ordres/{$ordre->id}/valider-n1")->assertOk();
});

it('agent ne peut pas valider N1', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->actingAs($agent)->postJson("/api/ordres/{$ordre->id}/valider-n1")->assertStatus(403);
});

it('directeur peut approuver DG', function () {
    $dg = User::factory()->directeur()->create();
    $ordre = OrdreMission::factory()->valideN1()->create(['taux_id' => $this->taux->id]);

    $this->actingAs($dg)->postJson("/api/ordres/{$ordre->id}/approuver")->assertOk();
});

it('chef service ne peut pas approuver DG', function () {
    $chef = User::factory()->chefService()->create();
    $ordre = OrdreMission::factory()->valideN1()->create(['taux_id' => $this->taux->id]);

    $this->actingAs($chef)->postJson("/api/ordres/{$ordre->id}/approuver")->assertStatus(403);
});

it('agent ne peut pas rejeter', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->actingAs($agent)->postJson("/api/ordres/{$ordre->id}/rejeter", [
        'commentaire' => 'Commentaire suffisamment long pour test',
    ])->assertStatus(403);
});

// ── Paiements ──

it('drh peut generer fichier paiement', function () {
    $drh = User::factory()->drh()->create();
    $agent = Agent::factory()->create(['numero_moov' => '22997000000']);
    $ordre = OrdreMission::factory()->approuveDG()->create(['agent_id' => $agent->id, 'taux_id' => $this->taux->id]);

    $this->actingAs($drh)->postJson('/api/paiements/generer/moov', [
        'ordre_ids' => [$ordre->id],
    ])->assertOk();
});

it('agent ne peut pas generer fichier paiement', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);

    $this->actingAs($agent)->postJson('/api/paiements/generer/moov', [
        'ordre_ids' => [1],
    ])->assertStatus(403);
});

// ── Taux ──

it('drh peut modifier un taux', function () {
    $drh = User::factory()->drh()->create();

    $this->actingAs($drh)->putJson("/api/taux/{$this->taux->id}", [
        'montant_journalier' => 30000,
    ])->assertOk();
});

it('agent ne peut pas modifier un taux', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);

    $this->actingAs($agent)->putJson("/api/taux/{$this->taux->id}", [
        'montant_journalier' => 30000,
    ])->assertStatus(403);
});

// ── Rapports ──

it('directeur peut voir les rapports', function () {
    $dg = User::factory()->directeur()->create();

    $this->actingAs($dg)->getJson('/api/rapports/mensuel')->assertOk();
});

it('agent ne peut pas voir les rapports', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);

    $this->actingAs($agent)->getJson('/api/rapports/mensuel')->assertStatus(403);
});

// ── Audit ──

it('directeur peut voir le journal audit', function () {
    $dg = User::factory()->directeur()->create();

    $this->actingAs($dg)->getJson('/api/audit')->assertOk();
});

it('agent ne peut pas voir le journal audit', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);

    $this->actingAs($agent)->getJson('/api/audit')->assertStatus(403);
});

it('chef service ne peut pas voir le journal audit', function () {
    $chef = User::factory()->chefService()->create();

    $this->actingAs($chef)->getJson('/api/audit')->assertStatus(403);
});
