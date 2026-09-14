<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\MissionService;
use Database\Seeders\BaremeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaremeSeeder::class);
    $this->service = app(MissionService::class);
    $this->user = User::factory()->gestionnaireOm()->create();
    $this->agent = Agent::factory()->categorie('INDICE_SUP_800')->create();
});

function donneesMission(Agent $agent, array $surcharge = []): array
{
    return array_merge([
        'objet' => 'Atelier',
        'destination' => 'Bohicon',
        'zone_code' => 'INTERIEUR',
        'date_depart' => '2026-06-01',
        'date_retour' => '2026-06-03',
        'prise_en_charge_tiers' => false,
        'ordres' => [
            ['reference_semo' => 'SEMO-A', 'agent_id' => $agent->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
        ],
    ], $surcharge);
}

it('crée la mission, les OM et leurs versements', function () {
    $mission = $this->service->creerAvecOrdres(donneesMission($this->agent), $this->user);

    expect($mission->ordresMission)->toHaveCount(1);

    $om = OrdreMission::where('reference_semo', 'SEMO-A')->first();
    expect($om->montant_total)->toBe(135000)
        ->and($om->statut)->toBe('IMPORTE')
        ->and(Versement::where('ordre_mission_id', $om->id)->count())->toBe(2);
});

it('reprend la catégorie de l’agent quand non fournie', function () {
    $cav = Agent::factory()->categorie('INDICE_INF_400')->create();
    $this->service->creerAvecOrdres(donneesMission($this->agent, [
        'ordres' => [
            ['reference_semo' => 'SEMO-CAV', 'agent_id' => $cav->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
        ],
    ]), $this->user);

    expect(OrdreMission::where('reference_semo', 'SEMO-CAV')->value('categorie'))->toBe('INDICE_INF_400');
});

it('n’alloue aucun versement si exonération', function () {
    $this->service->creerAvecOrdres(donneesMission($this->agent, ['prise_en_charge_tiers' => true]), $this->user);

    $om = OrdreMission::where('reference_semo', 'SEMO-A')->first();
    expect($om->montant_total)->toBe(0)
        ->and(Versement::where('ordre_mission_id', $om->id)->count())->toBe(0);
});

it('valide un OM (IMPORTE -> VALIDE)', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    expect($this->service->valider($om, $this->user)->statut)->toBe('VALIDE');
});

it('rejette un OM avec motif', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $result = $this->service->rejeter($om, 'Document non conforme', $this->user);
    expect($result->statut)->toBe('REJETE')
        ->and($result->commentaire_rejet)->toBe('Document non conforme');
});

it('refuse de valider un OM qui n’est pas IMPORTE', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VALIDE']);

    $this->service->valider($om, $this->user);
})->throws(ValidationException::class);
