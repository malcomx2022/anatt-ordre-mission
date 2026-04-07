<?php

use App\Models\Agent;
use App\Models\TauxPerDiem;
use App\Services\PerDiemCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->calculator = new PerDiemCalculator();
});

it('calcule le per diem correctement pour une mission locale', function () {
    $agent = Agent::factory()->create(['categorie' => 'A1']);
    TauxPerDiem::factory()->create([
        'categorie' => 'A1',
        'destination_type' => 'LOCALE',
        'montant_journalier' => 25000,
        'annee' => now()->year,
        'actif' => true,
    ]);

    $result = $this->calculator->calculer($agent, '2026-04-10', '2026-04-14', 'LOCALE');

    expect($result['nb_jours'])->toBe(5);
    expect($result['montant'])->toBe(125000.0);
    expect($result['taux'])->not->toBeNull();
    expect($result['erreur'])->toBeNull();
});

it('calcule nb_jours = 1 pour depart et retour le meme jour', function () {
    $agent = Agent::factory()->create(['categorie' => 'B']);
    TauxPerDiem::factory()->create([
        'categorie' => 'B',
        'destination_type' => 'LOCALE',
        'montant_journalier' => 15000,
        'annee' => now()->year,
        'actif' => true,
    ]);

    $result = $this->calculator->calculer($agent, '2026-04-10', '2026-04-10', 'LOCALE');

    expect($result['nb_jours'])->toBe(1);
    expect($result['montant'])->toBe(15000.0);
});

it('retourne erreur si aucun taux actif', function () {
    $agent = Agent::factory()->create(['categorie' => 'D']);

    $result = $this->calculator->calculer($agent, '2026-04-10', '2026-04-14', 'INTERNATIONALE');

    expect($result['montant'])->toBe(0);
    expect($result['erreur'])->not->toBeNull();
    expect($result['taux'])->toBeNull();
});

it('utilise le taux correspondant a la categorie agent', function () {
    $agentA1 = Agent::factory()->create(['categorie' => 'A1']);
    $agentC = Agent::factory()->create(['categorie' => 'C']);

    TauxPerDiem::factory()->create(['categorie' => 'A1', 'destination_type' => 'REGIONALE', 'montant_journalier' => 50000, 'annee' => now()->year]);
    TauxPerDiem::factory()->create(['categorie' => 'C', 'destination_type' => 'REGIONALE', 'montant_journalier' => 25000, 'annee' => now()->year]);

    $resultA1 = $this->calculator->calculer($agentA1, '2026-04-10', '2026-04-12', 'REGIONALE');
    $resultC = $this->calculator->calculer($agentC, '2026-04-10', '2026-04-12', 'REGIONALE');

    expect($resultA1['montant'])->toBe(150000.0); // 3 * 50000
    expect($resultC['montant'])->toBe(75000.0);   // 3 * 25000
});
