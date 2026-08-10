<?php

use App\Models\EtatPaiement;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;

/*
 * Séparation des tâches : les trois signataires du circuit (contrôle → visa DAF
 * → validation DG) doivent être TROIS personnes distinctes. On teste avec un
 * compte cumulant les permissions (super admin) — seul cas où une même main
 * pourrait enchaîner deux étapes.
 */

beforeEach(function () {
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->daf = User::factory()->daf()->create();
    $this->cumul = User::factory()->superAdmin()->create();

    $this->mission = Mission::factory()->create();
    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE',
        'montant_total' => 135000, 'avance' => 94500, 'reliquat' => 40500,
    ]);
});

/** Récapitule l'état de la mission et renvoie l'état en BROUILLON. */
function etatBrouillon(object $t): EtatPaiement
{
    $t->actingAs($t->gestionnaire)->postJson("/api/missions/{$t->mission->id}/etat/recapituler")->assertOk();

    return EtatPaiement::where('mission_id', $t->mission->id)->firstOrFail();
}

// ── Circuit des états ──

it('interdit au signataire du contrôle de viser le même état', function () {
    $etat = etatBrouillon($this);

    $this->actingAs($this->cumul)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->cumul)->postJson("/api/etats/{$etat->id}/viser")->assertStatus(422);
});

it('interdit au signataire du contrôle de valider définitivement le même état', function () {
    $etat = etatBrouillon($this);

    $this->actingAs($this->cumul)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $this->actingAs($this->cumul)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'ETAT-001'])->assertOk();

    // Le DG serait aussi le contrôleur : refusé.
    $this->actingAs($this->cumul)->postJson("/api/etats/{$etat->id}/approuver")->assertStatus(422);
});

// ── Confirmation de lot ──

it('refuse de confirmer un lot en erreur (à régénérer d’abord)', function () {
    $agent = User::factory()->agentFinancier()->create();
    $lot = LotPaiement::create([
        'mission_id' => $this->mission->id, 'tranche' => 'AVANCE',
        'operateur' => 'MTN', 'statut' => 'ERREUR',
    ]);

    $this->actingAs($agent)->postJson("/api/paiements/lots/{$lot->id}/confirmer")->assertStatus(422);
});
