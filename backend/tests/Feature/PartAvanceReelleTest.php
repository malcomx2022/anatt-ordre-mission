<?php

use App\Models\Mission;
use App\Models\OmAmendement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Support\Parametres;

/*
 * Part d'avance RÉELLE d'une mission : celle de ses OM, pas le réglage courant.
 *
 * La répartition est figée à la retranscription. Si le SUPER_ADMIN change la
 * part ensuite, les écrans qui décrivent une mission existante (état de
 * paiement, aperçu d'un lot) doivent afficher la part de ses OM — même règle
 * que les libellés du PDF de l'OM (PdfGenerator::libellesRepartition).
 */

beforeEach(function () {
    // Réglage courant : 70 %. La mission ci-dessous a été retranscrite à 60 %.
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();

    $this->mission = Mission::factory()->create();
    foreach ([[100000, 60000], [50000, 30000]] as [$total, $avance]) {
        OrdreMission::factory()->create([
            'mission_id' => $this->mission->id,
            'statut' => 'IMPORTE',
            'montant_total' => $total, 'avance' => $avance, 'reliquat' => $total - $avance,
        ]);
    }
});

afterEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();
});

it('tire la part des montants des OM, pas du réglage courant', function () {
    expect(Parametres::partAvance())->toBe(70)
        ->and($this->mission->partAvanceReelle())->toBe(60);
});

it('ignore les OM rejetés ou annulés', function () {
    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'REJETE',
        'montant_total' => 900000, 'avance' => 900000, 'reliquat' => 0,
    ]);

    expect($this->mission->partAvanceReelle())->toBe(60);
});

it('ignore un OM amendé dont l’avance est engagée (le reliquat a absorbé l’écart)', function () {
    $om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'VALIDE',
        // Avance de 60 % engagée sur 100 000, puis jours réduits : total 20 000.
        'montant_total' => 20000, 'avance' => 60000, 'avance_engagee' => 60000, 'reliquat' => -40000,
    ]);
    OmAmendement::create([
        'ordre_mission_id' => $om->id,
        'nb_jours_avant' => 10, 'nb_jours_apres' => 2,
        'montant_total_avant' => 100000, 'montant_total_apres' => 20000, 'delta_montant' => -80000,
        'motif' => 'Retour anticipé constaté.', 'amende_par' => User::factory()->create()->id,
        'amende_par_nom' => 'Test', 'amende_le' => now(),
    ]);

    expect($this->mission->fresh()->partAvanceReelle())->toBe(60);
});

it('renvoie null pour une mission sans montant (exonérée ou vide)', function () {
    $exoneree = Mission::factory()->create();
    OrdreMission::factory()->create([
        'mission_id' => $exoneree->id,
        'montant_total' => 0, 'avance' => 0, 'reliquat' => 0,
    ]);

    expect($exoneree->partAvanceReelle())->toBeNull()
        ->and(Mission::factory()->create()->partAvanceReelle())->toBeNull();
});

it('expose la part réelle sur les états de la mission', function () {
    $gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->actingAs($gestionnaire)
        ->postJson("/api/missions/{$this->mission->id}/etat/recapituler")
        ->assertOk()
        ->assertJsonPath('data.part_avance', 60);

    $this->actingAs($gestionnaire)
        ->getJson("/api/missions/{$this->mission->id}/etat")
        ->assertOk()
        ->assertJsonPath('data.avance.part_avance', 60);
});

it('expose la part réelle sur l’aperçu d’un lot', function () {
    $this->actingAs(User::factory()->agentFinancier()->create())
        ->getJson("/api/paiements/lots/previsualiser?mission_id={$this->mission->id}&tranche=AVANCE&operateur=MTN")
        ->assertOk()
        ->assertJsonPath('data.part_avance', 60);
});
