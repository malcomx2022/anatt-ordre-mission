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
});

it('calcule le per diem et répartit avance / reliquat', function () {
    $r = $this->service->calculer('ORDRE_1', 'INTERIEUR', 'JOURNEE_COMPLETE', 3);

    expect($r['montant_total'])->toBe(135000)   // 45000 x 3
        ->and($r['avance'])->toBe(45000)         // round(135000/3)
        ->and($r['reliquat'])->toBe(90000)       // 135000 - 45000
        ->and($r['exonere'])->toBeFalse();
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
