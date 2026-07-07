<?php

use App\Models\Bareme;
use App\Services\CalculIndemniteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CalculIndemniteService();
    Bareme::factory()->create([
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);
    Bareme::factory()->create([
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 15000,
    ]);
});

it('calcule le per diem et répartit avance / reliquat', function () {
    $r = $this->service->calculer('ORDRE_1', 'INTERIEUR', 'JOURNEE_COMPLETE', 3);

    expect($r['montant_total'])->toBe(135000)   // 45000 x 3
        ->and($r['avance'])->toBe(94500)         // round(135000 x 0,70)
        ->and($r['reliquat'])->toBe(40500)       // 135000 - 94500
        ->and($r['exonere'])->toBeFalse();
});

it('calcule un per diem détaillé multi-types (état ANaTT) + transport', function () {
    // Cas réel : 1 jour « 1 repas » (15000) + 6 jours « journée complète » (45000).
    $r = $this->service->calculerDetaille('ORDRE_1', 'INTERIEUR', [
        ['type' => 'UN_REPAS', 'nb_jours' => 1],
        ['type' => 'JOURNEE_COMPLETE', 'nb_jours' => 6],
    ], exonere: false, montantTransport: 10000);

    expect($r['per_diem'])->toBe(285000)             // 15000 + 270000
        ->and($r['montant_transport'])->toBe(10000)
        ->and($r['montant_total'])->toBe(295000)     // per_diem + transport
        ->and($r['nb_jours'])->toBe(7)               // 1 + 6
        ->and($r['type_principal'])->toBe('JOURNEE_COMPLETE') // type dominant (6 jours)
        ->and($r['avance'])->toBe(206500)            // round(295000 x 0,70)
        ->and($r['reliquat'])->toBe(88500)           // 295000 - 206500
        ->and($r['lignes'])->toHaveCount(2)
        ->and($r['exonere'])->toBeFalse();
});

it('ignore les lignes à zéro jour dans le calcul détaillé', function () {
    $r = $this->service->calculerDetaille('ORDRE_1', 'INTERIEUR', [
        ['type' => 'UN_REPAS', 'nb_jours' => 0],
        ['type' => 'JOURNEE_COMPLETE', 'nb_jours' => 3],
    ]);

    expect($r['lignes'])->toHaveCount(1)
        ->and($r['montant_total'])->toBe(135000)
        ->and($r['avance'])->toBe(94500);
});

it('exonère totalement en cas de prise en charge tiers', function () {
    $r = $this->service->calculer('ORDRE_1', 'INTERIEUR', 'JOURNEE_COMPLETE', 3, exonere: true);

    expect($r['montant_total'])->toBe(0)
        ->and($r['avance'])->toBe(0)
        ->and($r['reliquat'])->toBe(0)
        ->and($r['exonere'])->toBeTrue();
});

it('lève une erreur si le barème est introuvable', function () {
    $this->service->calculer('ORDRE_2', 'EXT_ZONE_I', 'LOGE_SEUL', 2);
})->throws(ValidationException::class);

it('compte les jours de façon inclusive (12/11 -> 14/11 = 3)', function () {
    expect($this->service->nbJours('2025-11-12', '2025-11-14'))->toBe(3);
});

it('compte 1 jour pour départ et retour le même jour', function () {
    expect($this->service->nbJours('2025-11-12', '2025-11-12'))->toBe(1);
});

it('applique la règle des 12 h : fraction >= 12 h = jour entier', function () {
    // 12/11 08h -> 13/11 21h = 37 h = 1 jour plein + 13 h (>= 12 h) => 2 jours.
    expect($this->service->nbJours(
        Carbon::parse('2025-11-12 08:00'),
        Carbon::parse('2025-11-13 21:00'),
    ))->toBe(2);
});

it('applique la règle des 12 h : retour 0-12 h ne compte pas un jour de plus', function () {
    // 12/11 08h -> 13/11 14h = 30 h = 1 jour plein + 6 h (< 12 h) => 1 jour.
    expect($this->service->nbJours(
        Carbon::parse('2025-11-12 08:00'),
        Carbon::parse('2025-11-13 14:00'),
    ))->toBe(1);
});
