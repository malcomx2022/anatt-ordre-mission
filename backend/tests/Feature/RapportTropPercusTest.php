<?php

use App\Models\Agent;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * État des trop-perçus (module Rapports) : synthèse des régularisations
 * (A_RECOUVRER / RECOUVRE), montants en valeur absolue, hors versements payés.
 * Cf. docs/amendement-om.md.
 */

/** Crée un versement de régularisation (reliquat négatif) au statut donné. */
function versementRegul(string $statut, int $montantAbs, string $operateur = 'MTN'): void
{
    $mission = Mission::factory()->create(['zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false]);
    $agent = Agent::factory()->create(['operateur' => $operateur]);
    $om = OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'agent_id' => $agent->id, 'statut' => 'VALIDE',
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 1, 'montant_total' => 1, 'avance' => 1, 'reliquat' => -$montantAbs, 'avance_engagee' => 1,
    ]);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id, 'type' => 'RELIQUAT', 'montant' => -$montantAbs,
        'operateur' => $operateur, 'statut' => $statut,
    ]);
}

it('synthétise les trop-perçus par statut et opérateur', function () {
    versementRegul('A_RECOUVRER', 10000, 'MTN');
    versementRegul('A_RECOUVRER', 20000, 'MOOV');
    versementRegul('RECOUVRE', 5000, 'MTN');
    // Un versement CONFIRME (payé) ne doit PAS entrer dans l'état des trop-perçus.
    $m = Mission::factory()->create();
    $a = Agent::factory()->create();
    $om = OrdreMission::factory()->create(['mission_id' => $m->id, 'agent_id' => $a->id, 'statut' => 'SOLDE']);
    Versement::factory()->create(['ordre_mission_id' => $om->id, 'type' => 'RELIQUAT', 'montant' => 99000, 'statut' => 'CONFIRME']);

    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/rapports/trop-percus')
        ->assertOk()
        ->assertJsonPath('data.a_recouvrer.nb', 2)
        ->assertJsonPath('data.a_recouvrer.montant', 30000)  // 10 000 + 20 000, valeur absolue
        ->assertJsonPath('data.recouvre.nb', 1)
        ->assertJsonPath('data.recouvre.montant', 5000)
        ->assertJsonPath('data.total.nb', 3)
        ->assertJsonPath('data.total.montant', 35000);
});

it('renvoie une synthèse à zéro quand il n’y a aucun trop-perçu', function () {
    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/rapports/trop-percus')
        ->assertOk()
        ->assertJsonPath('data.total.nb', 0)
        ->assertJsonPath('data.total.montant', 0)
        ->assertJsonPath('data.a_recouvrer.montant', 0);
});
