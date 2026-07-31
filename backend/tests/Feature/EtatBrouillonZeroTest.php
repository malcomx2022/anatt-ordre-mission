<?php

use App\Models\Agent;
use App\Models\Mission;
use App\Models\User;
use App\Services\EtatPaiementService;
use App\Services\MissionService;
use Database\Seeders\BaremeSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * Reproduction du signalement : sur un état à montants nuls (mission exonérée —
 * prise en charge par un tiers), le brouillon doit rester téléchargeable et les
 * montants (0) s'afficher, pas planter.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->seed(ZoneSeeder::class);
    $this->seed(BaremeSeeder::class);
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

/** Crée une mission exonérée (prise en charge tiers) : OMs à montants nuls. */
function missionExoneree(User $user): Mission
{
    $agent = Agent::factory()->create(['indice' => 500, 'telephone' => '0190000000', 'operateur' => 'MTN']);

    return app(MissionService::class)->creerAvecOrdres([
        'objet' => 'Atelier pris en charge par un partenaire',
        'destination' => 'Cotonou',
        'zone_code' => 'INTERIEUR',
        'date_depart' => now()->subDays(3)->toDateString(),
        'date_retour' => now()->subDay()->toDateString(),
        'prise_en_charge_tiers' => true,
        'ordres' => [[
            'reference_semo' => 'EXO-001', 'agent_id' => $agent->id,
            'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        ]],
    ], $user);
}

it('récapitule un état exonéré à montants nuls', function () {
    $mission = missionExoneree($this->gestion);
    $etat = app(EtatPaiementService::class)->recapituler($mission, $this->gestion);

    expect($etat->nb_beneficiaires)->toBe(1)
        ->and($etat->montant_total)->toBe(0)
        ->and($etat->montant_avance)->toBe(0);
});

it('expose le montant en lettres et les montants dans la réponse API', function () {
    $mission = missionExoneree($this->gestion);
    app(EtatPaiementService::class)->recapituler($mission, $this->gestion);

    $this->actingAs($this->gestion)->getJson("/api/missions/{$mission->id}/etat")
        ->assertOk()
        ->assertJsonPath('data.avance.nb_beneficiaires', 1)
        ->assertJsonPath('data.avance.montant_total', 0)
        ->assertJsonPath('data.avance.montant_avance', 0)
        // Le montant en lettres ne doit jamais être vide, même à 0.
        ->assertJsonPath('data.avance.montant_total_lettres', fn ($v) => is_string($v) && $v !== '');
});

it('télécharge le brouillon d’un état à montants nuls', function () {
    $mission = missionExoneree($this->gestion);
    app(EtatPaiementService::class)->recapituler($mission, $this->gestion);

    $reponse = $this->actingAs($this->gestion)->get("/api/missions/{$mission->id}/etat/brouillon");

    $reponse->assertOk();
    expect($reponse->headers->get('content-type'))->toContain('application/pdf');
});

it('télécharge le brouillon d’un état normal', function () {
    $agent = Agent::factory()->create(['indice' => 850, 'telephone' => '0190000001', 'operateur' => 'MTN']);
    $mission = app(MissionService::class)->creerAvecOrdres([
        'objet' => 'Supervision', 'destination' => 'Parakou', 'zone_code' => 'INTERIEUR',
        'date_depart' => now()->subDays(3)->toDateString(), 'date_retour' => now()->subDay()->toDateString(),
        'prise_en_charge_tiers' => false,
        'ordres' => [[
            'reference_semo' => 'OK-001', 'agent_id' => $agent->id,
            'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        ]],
    ], $this->gestion);
    app(EtatPaiementService::class)->recapituler($mission, $this->gestion);

    $this->actingAs($this->gestion)->get("/api/missions/{$mission->id}/etat/brouillon")
        ->assertOk();
});
