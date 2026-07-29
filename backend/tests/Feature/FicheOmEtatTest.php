<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;

/*
 * La fiche d'un ordre de mission doit exposer l'état de paiement de SA mission :
 * le circuit de validation (contrôle -> visa DAF -> validation DG) se joue sur
 * l'état, pas sur l'OM. Sans cette information jointe, la fiche ne pourrait pas
 * renvoyer vers l'état ni afficher où en est la validation.
 */

beforeEach(function () {
    $this->lecteur = User::factory()->lecteur()->create();
    $this->mission = Mission::factory()->create();
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'IMPORTE',
    ]);
});

it('joint l’état de paiement de la mission à la fiche OM', function () {
    EtatPaiement::create([
        'mission_id' => $this->mission->id,
        'numero' => '0071/ANaTT/DAF/C-SFC',
        'statut' => 'VISA_DAF',
        'nb_beneficiaires' => 1,
        'montant_total' => 100000,
        'montant_avance' => 70000,
        'montant_reliquat' => 30000,
    ]);

    $this->actingAs($this->lecteur)->getJson("/api/ordres/{$this->om->id}")
        ->assertOk()
        ->assertJsonPath('data.mission.etat_paiement.statut', 'VISA_DAF')
        ->assertJsonPath('data.mission.etat_paiement.numero', '0071/ANaTT/DAF/C-SFC')
        ->assertJsonPath('data.mission.etat_paiement.valide', false);
});

it('signale l’absence d’état par une valeur nulle', function () {
    // Mission sans récapitulatif : la fiche affichera « à établir ».
    $this->actingAs($this->lecteur)->getJson("/api/ordres/{$this->om->id}")
        ->assertOk()
        ->assertJsonPath('data.mission.etat_paiement', null);
});

it('marque l’état comme validé une fois arrêté par le DG', function () {
    EtatPaiement::create([
        'mission_id' => $this->mission->id,
        'numero' => '0072/ANaTT/DAF/C-SFC',
        'statut' => 'VALIDE',
        'nb_beneficiaires' => 1,
        'montant_total' => 100000,
        'montant_avance' => 70000,
        'montant_reliquat' => 30000,
    ]);

    $this->actingAs($this->lecteur)->getJson("/api/ordres/{$this->om->id}")
        ->assertOk()
        ->assertJsonPath('data.mission.etat_paiement.valide', true);
});
