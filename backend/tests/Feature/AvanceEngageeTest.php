<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;

/*
 * Gel de l'AVANCE ENGAGÉE : au moment où l'état des AVANCES passe VALIDÉ, chaque
 * OM de la mission mémorise son avance dans `avance_engagee`. C'est le socle de
 * l'amendement post-paiement (le reliquat s'ajustera contre ce montant figé).
 * Cf. docs/amendement-om.md.
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create();
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();

    $this->mission = Mission::factory()->create();
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE',
        'montant_total' => 135000, 'avance' => 94500, 'reliquat' => 40500,
    ]);
});

/** Établit l'état des avances et renvoie l'état en BROUILLON. */
function etatAvancesBrouillon(object $t): EtatPaiement
{
    $t->actingAs($t->gestionnaire)->postJson("/api/missions/{$t->mission->id}/etat/recapituler")->assertOk();

    return EtatPaiement::where('mission_id', $t->mission->id)->firstOrFail();
}

it('laisse avance_engagee à null tant que l’état des avances n’est pas validé', function () {
    $etat = etatAvancesBrouillon($this);
    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();

    // Contrôlé + visé, mais pas encore validé par le DG : rien n'est figé.
    expect($this->om->fresh()->avance_engagee)->toBeNull();
});

it('fige avance_engagee = avance à la validation finale des avances', function () {
    $etat = etatAvancesBrouillon($this);
    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $this->actingAs($this->dg)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'AV-2026-001'])->assertOk();
    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    $om = $this->om->fresh();
    expect($om->statut)->toBe('VALIDE')
        ->and($om->avance_engagee)->toBe(94500)
        ->and($om->avance_engagee)->toBe($om->avance);
});
