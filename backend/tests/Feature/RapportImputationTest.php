<?php

use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * Rapport par imputation : une imputation absente est normalisée à la source
 * en « (non renseignée) », pour que l'endpoint JSON soit cohérent avec l'export
 * et l'UI (aucun consommateur n'a à recoller l'étiquette).
 */

it('renvoie « (non renseignée) » pour une imputation absente', function () {
    $mission = Mission::factory()->create(['imputation_budgetaire' => null]);
    $ordre = OrdreMission::factory()->create(['mission_id' => $mission->id]);
    Versement::factory()->confirme()->create(['ordre_mission_id' => $ordre->id]);

    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/rapports/imputations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.imputation_budgetaire', '(non renseignée)');
});
