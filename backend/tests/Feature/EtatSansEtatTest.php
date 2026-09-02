<?php

use App\Models\Mission;
use App\Models\User;

it('renvoie data:null pour une mission sans etat', function () {
    $u = User::factory()->gestionnaireOm()->create();
    $m = Mission::factory()->create();

    $rep = $this->actingAs($u)->getJson("/api/missions/{$m->id}/etat")->assertOk();
    $rep->assertJsonPath('data', null);
});
