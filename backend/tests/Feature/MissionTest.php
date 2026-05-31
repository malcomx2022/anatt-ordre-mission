<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use Database\Seeders\BaremeSeeder;

beforeEach(function () {
    $this->seed(BaremeSeeder::class);
    $this->gestion = User::factory()->gestionnaireOm()->create();
    $this->dada = Agent::factory()->categorie('ORDRE_1')->create();
    $this->cav = Agent::factory()->categorie('ORDRE_3')->moov()->create();
});

function missionPayload(array $agents): array
{
    return [
        'objet' => 'Atelier régional',
        'destination' => 'Bohicon',
        'zone_code' => 'INTERIEUR',
        'date_depart' => '2026-06-01',
        'date_retour' => '2026-06-03',
        'ordres' => $agents,
    ];
}

it('retranscrit une mission en N OM avec versements', function () {
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        ['reference_semo' => 'SEMO-1', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
        ['reference_semo' => 'SEMO-2', 'agent_id' => $this->cav->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
    ]))->assertStatus(201);

    expect(OrdreMission::count())->toBe(2);

    $om = OrdreMission::where('reference_semo', 'SEMO-1')->first();
    // ORDRE_1 / INTERIEUR / JOURNEE_COMPLETE = 45000 x 3 = 135000
    expect($om->montant_total)->toBe(135000)
        ->and($om->avance)->toBe(45000)
        ->and($om->reliquat)->toBe(90000)
        ->and($om->statut)->toBe('IMPORTE');

    expect(Versement::where('ordre_mission_id', $om->id)->count())->toBe(2);
});

it('le CAV est payé selon SA catégorie', function () {
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        ['reference_semo' => 'SEMO-CAV', 'agent_id' => $this->cav->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
    ]))->assertStatus(201);

    // ORDRE_3 / INTERIEUR / JOURNEE_COMPLETE = 30000 x 3 = 90000
    expect(OrdreMission::where('reference_semo', 'SEMO-CAV')->value('montant_total'))->toBe(90000);
});

it('refuse une référence SEMO en doublon', function () {
    OrdreMission::factory()->create(['reference_semo' => 'SEMO-DUP']);

    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        ['reference_semo' => 'SEMO-DUP', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
    ]))->assertStatus(422)->assertJsonValidationErrors(['ordres.0.reference_semo']);
});

it('refuse un type incompatible avec la zone', function () {
    // LOGE_SEUL (hébergement) interdit en INTERIEUR.
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        ['reference_semo' => 'SEMO-X', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'LOGE_SEUL'],
    ]))->assertStatus(422);
});

it('exonère les versements en cas de prise en charge tiers', function () {
    $payload = missionPayload([
        ['reference_semo' => 'SEMO-EX', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
    ]);
    $payload['prise_en_charge_tiers'] = true;

    $this->actingAs($this->gestion)->postJson('/api/missions', $payload)->assertStatus(201);

    $om = OrdreMission::where('reference_semo', 'SEMO-EX')->first();
    expect($om->montant_total)->toBe(0)
        ->and(Versement::where('ordre_mission_id', $om->id)->count())->toBe(0);
});

it('liste les ordres de mission', function () {
    OrdreMission::factory()->count(3)->create();

    $this->actingAs($this->gestion)->getJson('/api/ordres')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('valide un OM (IMPORTE -> VALIDE)', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->gestion)->postJson("/api/ordres/{$om->id}/valider")
        ->assertOk()
        ->assertJsonPath('data.statut', 'VALIDE');
});

it('rejette un OM avec motif', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->gestion)->postJson("/api/ordres/{$om->id}/rejeter", [
        'motif' => 'Document non conforme au dossier',
    ])->assertOk()->assertJsonPath('data.statut', 'REJETE');
});

it('refuse un rejet sans motif suffisant', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->gestion)->postJson("/api/ordres/{$om->id}/rejeter", ['motif' => 'court'])
        ->assertStatus(422);
});
