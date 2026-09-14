<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use Database\Seeders\BaremeSeeder;

beforeEach(function () {
    $this->seed(BaremeSeeder::class);
    $this->gestion = User::factory()->gestionnaireOm()->create();   // retranscription (om.retranscrire)
    $this->controleur = User::factory()->controleur()->create();     // validation/rejet (om.valider)
    $this->dada = Agent::factory()->categorie('INDICE_SUP_800')->create();
    $this->cav = Agent::factory()->categorie('INDICE_INF_400')->moov()->create();
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
    // INDICE_SUP_800 / INTERIEUR / JOURNEE_COMPLETE = 45000 x 3 = 135000
    expect($om->montant_total)->toBe(135000)
        ->and($om->avance)->toBe(94500)     // 70 %
        ->and($om->reliquat)->toBe(40500)   // 30 %
        ->and($om->statut)->toBe('IMPORTE');

    expect(Versement::where('ordre_mission_id', $om->id)->count())->toBe(2);
});

it('retranscrit en répartition détaillée sans type_prise_en_charge (mode lignes)', function () {
    // En mode détaillé, ce sont les lignes qui portent les types : le champ
    // type_prise_en_charge n'a pas à être fourni.
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        [
            'reference_semo' => 'SEMO-DET', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT',
            'lignes' => [
                ['type' => 'UN_REPAS', 'nb_jours' => 1],
                ['type' => 'JOURNEE_COMPLETE', 'nb_jours' => 2],
            ],
        ],
    ]))->assertStatus(201);

    $om = OrdreMission::where('reference_semo', 'SEMO-DET')->firstOrFail();
    // INDICE_SUP_800 / INTERIEUR : 15000 x1 (un repas) + 45000 x2 (journée) = 105000
    expect($om->montant_total)->toBe(105000)
        ->and($om->type_prise_en_charge)->not->toBeNull()
        ->and($om->statut)->toBe('IMPORTE');
});

it('accepte un type_prise_en_charge vide quand des lignes sont fournies (payload UI)', function () {
    // Régression : l'interface envoie type_prise_en_charge = '' en mode détaillé.
    // La chaîne vide ne doit pas déclencher « le champ est obligatoire ».
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        [
            'reference_semo' => 'SEMO-VIDE', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT',
            'type_prise_en_charge' => '',
            'lignes' => [['type' => 'JOURNEE_COMPLETE', 'nb_jours' => 2]],
        ],
    ]))->assertStatus(201);

    expect(OrdreMission::where('reference_semo', 'SEMO-VIDE')->exists())->toBeTrue();
});

it('exige le type de prise en charge en l’absence de lignes', function () {
    // Mode simple : sans lignes ET sans type, la requête est invalide.
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        ['reference_semo' => 'SEMO-NIL', 'agent_id' => $this->dada->id, 'role' => 'PARTICIPANT'],
    ]))->assertStatus(422)->assertJsonValidationErrors('ordres.0.type_prise_en_charge');
});

it('le CAV est payé selon SA catégorie', function () {
    $this->actingAs($this->gestion)->postJson('/api/missions', missionPayload([
        ['reference_semo' => 'SEMO-CAV', 'agent_id' => $this->cav->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
    ]))->assertStatus(201);

    // INDICE_INF_400 / INTERIEUR / JOURNEE_COMPLETE = 30000 x 3 = 90000
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

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertOk()
        ->assertJsonPath('data.statut', 'VALIDE');
});

it('rejette un OM avec motif', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/rejeter", [
        'motif' => 'Document non conforme au dossier',
    ])->assertOk()->assertJsonPath('data.statut', 'REJETE');
});

it('refuse un rejet sans motif suffisant', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/rejeter", ['motif' => 'court'])
        ->assertStatus(422);
});
