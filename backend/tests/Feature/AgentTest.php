<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

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

it('recalcule la catégorie quand l’indice change, malgré l’ancienne catégorie envoyée', function () {
    $agent = Agent::factory()->create(['indice' => 850, 'salaire_brut' => null, 'categorie' => 'ORDRE_1']);

    // Le formulaire d'édition renvoie l'ancienne catégorie : elle ne doit pas
    // figer le calcul. Le nouvel indice (300) fait basculer en ORDRE_3.
    $this->actingAs($this->gestion)->putJson("/api/agents/{$agent->id}", [
        'indice' => 300, 'categorie' => 'ORDRE_1',
    ])->assertOk()
      ->assertJsonPath('data.categorie', 'ORDRE_3');

    expect($agent->fresh()->categorie)->toBe('ORDRE_3');
});

it('recalcule la catégorie depuis le salaire brut modifié', function () {
    $agent = Agent::factory()->create(['indice' => null, 'salaire_brut' => 150000, 'categorie' => 'ORDRE_3']);

    $this->actingAs($this->gestion)->putJson("/api/agents/{$agent->id}", [
        'salaire_brut' => 400000, 'categorie' => 'ORDRE_3',
    ])->assertOk()
      ->assertJsonPath('data.categorie', 'ORDRE_1');
});

it('respecte la catégorie transmise quand ni indice ni salaire ne changent', function () {
    // Reprise de données : agent sans indice ni salaire, catégorie posée à la main.
    $agent = Agent::factory()->create(['indice' => null, 'salaire_brut' => null, 'categorie' => 'ORDRE_2']);

    $this->actingAs($this->gestion)->putJson("/api/agents/{$agent->id}", [
        'categorie' => 'ORDRE_1',
    ])->assertOk()
      ->assertJsonPath('data.categorie', 'ORDRE_1');
});

it('synchronise le téléphone et l’opérateur des versements EN_ATTENTE', function () {
    $agent = Agent::factory()->create([
        'telephone' => '0190000000', 'operateur' => 'MTN', 'indice' => 500,
    ]);
    // Un OM par versement : la clé (ordre_mission_id, type) est unique.
    $om = fn () => OrdreMission::factory()->create(['agent_id' => $agent->id])->id;

    $enAttente = Versement::factory()->create([
        'ordre_mission_id' => $om(), 'statut' => 'EN_ATTENTE',
        'telephone' => '0190000000', 'operateur' => 'MTN',
    ]);
    // Un versement déjà généré et un confirmé : figés, jamais réécrits.
    $genere = Versement::factory()->create([
        'ordre_mission_id' => $om(), 'statut' => 'GENERE',
        'telephone' => '0190000000', 'operateur' => 'MTN',
    ]);
    $confirme = Versement::factory()->confirme()->create([
        'ordre_mission_id' => $om(),
        'telephone' => '0190000000', 'operateur' => 'MTN',
    ]);

    // 014X -> Moov : nouveau numéro cohérent avec le nouvel opérateur.
    $this->actingAs($this->gestion)->putJson("/api/agents/{$agent->id}", [
        'telephone' => '0140000000', 'operateur' => 'MOOV',
    ])->assertOk();

    expect($enAttente->fresh())
        ->telephone->toBe('0140000000')
        ->operateur->toBe('MOOV');

    // Les versements verrouillés gardent les anciennes coordonnées.
    expect($genere->fresh())->telephone->toBe('0190000000')->operateur->toBe('MTN');
    expect($confirme->fresh())->telephone->toBe('0190000000')->operateur->toBe('MTN');
});

it('ne touche pas aux versements d’un autre agent', function () {
    $autre = Agent::factory()->create(['telephone' => '0196000000', 'operateur' => 'MTN', 'indice' => 500]);
    $ordreAutre = OrdreMission::factory()->create(['agent_id' => $autre->id]);
    $versementAutre = Versement::factory()->create([
        'ordre_mission_id' => $ordreAutre->id, 'statut' => 'EN_ATTENTE',
        'telephone' => '0196000000', 'operateur' => 'MTN',
    ]);

    $cible = Agent::factory()->create(['telephone' => '0190000000', 'operateur' => 'MTN', 'indice' => 500]);

    $this->actingAs($this->gestion)->putJson("/api/agents/{$cible->id}", [
        'telephone' => '0140000000', 'operateur' => 'MOOV',
    ])->assertOk();

    expect($versementAutre->fresh())->telephone->toBe('0196000000')->operateur->toBe('MTN');
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
