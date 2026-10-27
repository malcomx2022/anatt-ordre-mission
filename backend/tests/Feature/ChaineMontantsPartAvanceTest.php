<?php

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Support\Parametres;
use Database\Seeders\BaremeSeeder;

/*
 * Chaîne complète des montants sous une part d'avance NON standard (55 %) :
 * retranscription → versements → état des avances → aperçu des lots → état des
 * reliquats. Chaque maillon doit porter les montants calculés avec la part en
 * vigueur À LA RETRANSCRIPTION, et la part affichée doit rester celle de la
 * mission quand le réglage change ensuite (70 %).
 */

beforeEach(function () {
    $this->seed(BaremeSeeder::class);
    file_put_contents(storage_path('app/settings.json'), json_encode(['partAvance' => 55]));
    Parametres::invalidateCache();

    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->gestion = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create();
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();
    $this->finance = User::factory()->agentFinancier()->create();
    $this->mtn = Agent::factory()->categorie('INDICE_SUP_800')->mtn()->create();
    $this->moov = Agent::factory()->categorie('INDICE_INF_400')->moov()->create();
});

afterEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();
});

it('aligne montants et pourcentages sur la part en vigueur à la retranscription', function () {
    $missionId = $this->actingAs($this->gestion)->postJson('/api/missions', [
        'objet' => 'Contrôle routier', 'destination' => 'Bohicon', 'zone_code' => 'INTERIEUR',
        'date_depart' => '2026-06-01', 'date_retour' => '2026-06-03',
        'ordres' => [
            ['reference_semo' => 'SEMO-55-1', 'agent_id' => $this->mtn->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
            ['reference_semo' => 'SEMO-55-2', 'agent_id' => $this->moov->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
        ],
    ])->assertStatus(201)->json('data.id');

    // 1. Chaque OM et ses deux versements : avance = round(total × 55 %), reliquat = le reste.
    $ordres = OrdreMission::where('mission_id', $missionId)->get();
    foreach ($ordres as $om) {
        $avance = (int) round($om->montant_total * 55 / 100);
        expect($om->avance)->toBe($avance)
            ->and($om->reliquat)->toBe($om->montant_total - $avance)
            ->and(Versement::where('ordre_mission_id', $om->id)->where('type', 'AVANCE')->value('montant'))->toBe($avance)
            ->and(Versement::where('ordre_mission_id', $om->id)->where('type', 'RELIQUAT')->value('montant'))->toBe($om->montant_total - $avance);
    }
    $sommeAvances = (int) $ordres->sum('avance');
    $sommeReliquats = (int) $ordres->sum('reliquat');

    // 2. Le réglage passe à 70 % : les OM retranscrits ne bougent pas.
    $this->actingAs($this->superAdmin)->putJson('/api/admin/settings', ['partAvance' => 70])->assertOk();
    expect(Parametres::partAvance())->toBe(70)
        ->and((int) OrdreMission::where('mission_id', $missionId)->sum('avance'))->toBe($sommeAvances);

    // 3. État des avances : Σ des avances, et la part de la mission (55), pas le réglage (70).
    $this->actingAs($this->gestion)->postJson("/api/missions/{$missionId}/etat/recapituler")
        ->assertOk()
        ->assertJsonPath('data.montant_total', $sommeAvances)
        ->assertJsonPath('data.part_avance', 55);

    $etat = EtatPaiement::where('mission_id', $missionId)->where('tranche', 'AVANCE')->firstOrFail();
    $this->actingAs($this->controleur)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'EP-55'])->assertOk();
    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    // 4. Aperçu des lots d'avance : un bénéficiaire par opérateur, au montant de son avance.
    $totalApercus = 0;
    foreach (['MTN', 'MOOV'] as $operateur) {
        $apercu = $this->actingAs($this->finance)
            ->getJson("/api/paiements/lots/previsualiser?mission_id={$missionId}&tranche=AVANCE&operateur={$operateur}")
            ->assertOk()
            ->assertJsonPath('data.part_avance', 55)
            ->assertJsonPath('data.nb_lignes', 1)
            ->json('data');
        $totalApercus += $apercu['total'];
    }
    expect($totalApercus)->toBe($sommeAvances);

    // 5. État des reliquats : Σ des reliquats (aucun trop-perçu ici), même part affichée.
    $this->actingAs($this->gestion)->postJson("/api/missions/{$missionId}/etat/recapituler", ['tranche' => 'RELIQUAT'])
        ->assertOk()
        ->assertJsonPath('data.montant_total', $sommeReliquats)
        ->assertJsonPath('data.part_avance', 55);

    expect($sommeAvances + $sommeReliquats)->toBe((int) $ordres->sum('montant_total'));
});
