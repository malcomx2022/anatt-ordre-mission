<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;

/*
 * Liste des missions : point d'entrée du circuit.
 * Chaque ligne porte le compte d'OM, le montant total et l'avancement de son état.
 */

beforeEach(function () {
    $this->lecteur = User::factory()->lecteur()->create();

    $this->mission = Mission::factory()->create([
        'objet' => 'Vérification des hangars',
        'destination' => 'PARAKOU',
    ]);

    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE', 'montant_total' => 210000,
    ]);
    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'IMPORTE', 'montant_total' => 135000,
    ]);
});

it('agrège les ordres de mission de chaque mission', function () {
    $this->actingAs($this->lecteur)->getJson('/api/missions')
        ->assertOk()
        ->assertJsonPath('data.0.nb_ordres', 2)
        ->assertJsonPath('data.0.montant_total', 345000)
        ->assertJsonPath('data.0.objet', 'Vérification des hangars');
});

it('exclut les OM rejetés ou annulés des agrégats', function () {
    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'REJETE', 'montant_total' => 999000,
    ]);

    $this->actingAs($this->lecteur)->getJson('/api/missions')
        ->assertOk()
        ->assertJsonPath('data.0.nb_ordres', 2)
        ->assertJsonPath('data.0.montant_total', 345000);
});

it('indique qu’aucun état n’existe encore', function () {
    $this->actingAs($this->lecteur)->getJson('/api/missions')
        ->assertOk()
        ->assertJsonPath('data.0.etat_statut', null)
        ->assertJsonPath('data.0.etat_numero', null);
});

it('remonte l’avancement de l’état quand il existe', function () {
    EtatPaiement::create([
        'mission_id' => $this->mission->id,
        'numero' => '0069/ANaTT/DAF/C-SFC',
        'statut' => 'VISA_DAF',
        'nb_beneficiaires' => 2,
        'montant_total' => 345000,
        'montant_avance' => 241500,
        'montant_reliquat' => 103500,
    ]);

    $this->actingAs($this->lecteur)->getJson('/api/missions')
        ->assertOk()
        ->assertJsonPath('data.0.etat_statut', 'VISA_DAF')
        ->assertJsonPath('data.0.etat_numero', '0069/ANaTT/DAF/C-SFC');
});

it('distingue l’état des avances de l’état des reliquats', function () {
    // Deux tranches au même instant, à des étapes différentes du circuit.
    EtatPaiement::create([
        'mission_id' => $this->mission->id, 'tranche' => 'AVANCE', 'statut' => 'VALIDE',
        'numero' => 'AV-001',
        'nb_beneficiaires' => 2, 'montant_total' => 241500, 'montant_avance' => 241500, 'montant_reliquat' => 0,
    ]);
    EtatPaiement::create([
        'mission_id' => $this->mission->id, 'tranche' => 'RELIQUAT', 'statut' => 'CONTROLE',
        'numero' => 'RL-001',
        'nb_beneficiaires' => 2, 'montant_total' => 103500, 'montant_avance' => 0, 'montant_reliquat' => 103500,
    ]);

    // La colonne avance porte l'état AVANCE, la colonne reliquat porte le RELIQUAT
    // (et non un écho de l'avance) : extraction explicite par tranche.
    $this->actingAs($this->lecteur)->getJson('/api/missions')
        ->assertOk()
        ->assertJsonPath('data.0.etat_statut', 'VALIDE')
        ->assertJsonPath('data.0.etat_numero', 'AV-001')
        ->assertJsonPath('data.0.etat_reliquat_statut', 'CONTROLE')
        ->assertJsonPath('data.0.etat_reliquat_numero', 'RL-001');
});

it('filtre les missions dont le récapitulatif reste à établir', function () {
    $avecEtat = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $avecEtat->id, 'statut' => 'IMPORTE']);
    EtatPaiement::create([
        'mission_id' => $avecEtat->id, 'statut' => 'BROUILLON',
        'nb_beneficiaires' => 1, 'montant_total' => 1, 'montant_avance' => 1, 'montant_reliquat' => 0,
    ]);

    // Seule la mission sans état remonte.
    $this->actingAs($this->lecteur)->getJson('/api/missions?etat=sans_etat')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->mission->id);

    $this->actingAs($this->lecteur)->getJson('/api/missions?etat=BROUILLON')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $avecEtat->id);
});

it('recherche par objet, destination ou imputation', function () {
    foreach (['hangars', 'PARAKOU'] as $terme) {
        $this->actingAs($this->lecteur)->getJson("/api/missions?search={$terme}")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    $this->actingAs($this->lecteur)->getJson('/api/missions?search=introuvable')
        ->assertOk()->assertJsonCount(0, 'data');
});

it('exige la permission de consulter les OM', function () {
    $this->getJson('/api/missions')->assertUnauthorized();
});
