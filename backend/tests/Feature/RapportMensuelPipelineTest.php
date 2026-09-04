<?php

use App\Models\Agent;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * État mensuel — bloc « Pipeline (tous statuts) ».
 *
 * Le pipeline reflète le circuit Mobile Money (EN_ATTENTE / GENERE / CONFIRME /
 * ANNULE). Les régularisations (A_RECOUVRER / RECOUVRE — trop-perçus, montants
 * négatifs, retenue sur salaire) en sont EXCLUES : elles ont leur propre état.
 */

/** Versement au statut donné, rattaché à un OM/mission/agent neufs. */
function versementStatut(string $statut, int $montant, ?string $datePaiement = null): void
{
    $mission = Mission::factory()->create();
    $agent = Agent::factory()->create();
    $om = OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'agent_id' => $agent->id, 'statut' => 'VALIDE',
    ]);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id, 'type' => 'RELIQUAT', 'montant' => $montant,
        'statut' => $statut, 'date_paiement' => $datePaiement,
    ]);
}

it('exclut les régularisations du pipeline de l’état mensuel', function () {
    $mois = now()->format('Y-m');
    versementStatut('CONFIRME', 200000, now()->toDateTimeString());
    versementStatut('EN_ATTENTE', 50000);
    // Trop-perçus : ne doivent PAS apparaître dans le pipeline.
    versementStatut('A_RECOUVRER', -30000);
    versementStatut('RECOUVRE', -10000);

    $data = $this->actingAs(User::factory()->lecteur()->create())
        ->getJson("/api/rapports/mensuel?mois={$mois}")
        ->assertOk()
        ->json('data');

    $statuts = collect($data['par_statut'])->pluck('statut')->all();

    expect($statuts)->toContain('CONFIRME')
        ->and($statuts)->toContain('EN_ATTENTE')
        ->and($statuts)->not->toContain('A_RECOUVRER')
        ->and($statuts)->not->toContain('RECOUVRE');
});
