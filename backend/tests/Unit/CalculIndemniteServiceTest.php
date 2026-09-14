<?php

use App\Models\Bareme;
use App\Services\CalculIndemniteService;
use App\Support\Parametres;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Fixe temporairement la part d'avance dans settings.json et purge le cache. */
function definirPartAvance(?int $valeur): void
{
    $chemin = storage_path('app/settings.json');

    if ($valeur === null) {
        @unlink($chemin);
    } else {
        @mkdir(dirname($chemin), 0775, true);
        file_put_contents($chemin, json_encode(['partAvance' => $valeur]));
    }

    Parametres::invalidateCache();
}

beforeEach(function () {
    $this->service = new CalculIndemniteService;
    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);
    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 15000,
    ]);
});

it('calcule le per diem et répartit avance / reliquat', function () {
    $r = $this->service->calculer('INDICE_SUP_800', 'INTERIEUR', 'JOURNEE_COMPLETE', 3);

    expect($r['montant_total'])->toBe(135000)   // 45000 x 3
        ->and($r['avance'])->toBe(94500)         // round(135000 x 0,70)
        ->and($r['reliquat'])->toBe(40500)       // 135000 - 94500
        ->and($r['exonere'])->toBeFalse();
});

it('calcule un per diem détaillé multi-types (état ANaTT) + transport', function () {
    // Cas réel : 1 jour « 1 repas » (15000) + 6 jours « journée complète » (45000).
    $r = $this->service->calculerDetaille('INDICE_SUP_800', 'INTERIEUR', [
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
    $r = $this->service->calculerDetaille('INDICE_SUP_800', 'INTERIEUR', [
        ['type' => 'UN_REPAS', 'nb_jours' => 0],
        ['type' => 'JOURNEE_COMPLETE', 'nb_jours' => 3],
    ]);

    expect($r['lignes'])->toHaveCount(1)
        ->and($r['montant_total'])->toBe(135000)
        ->and($r['avance'])->toBe(94500);
});

it('respecte la part d’avance paramétrable (≠ 70 %)', function () {
    definirPartAvance(60);

    try {
        $simple = $this->service->calculer('INDICE_SUP_800', 'INTERIEUR', 'JOURNEE_COMPLETE', 3);
        $detaille = $this->service->calculerDetaille('INDICE_SUP_800', 'INTERIEUR', [
            ['type' => 'JOURNEE_COMPLETE', 'nb_jours' => 3],
        ]);

        expect($simple['avance'])->toBe(81000)        // round(135000 x 0,60)
            ->and($simple['reliquat'])->toBe(54000)   // 135000 - 81000
            ->and($detaille['avance'])->toBe(81000)
            ->and($detaille['reliquat'])->toBe(54000);
    } finally {
        definirPartAvance(null);
    }
});

it('exonère totalement en cas de prise en charge tiers', function () {
    $r = $this->service->calculer('INDICE_SUP_800', 'INTERIEUR', 'JOURNEE_COMPLETE', 3, exonere: true);

    expect($r['montant_total'])->toBe(0)
        ->and($r['avance'])->toBe(0)
        ->and($r['reliquat'])->toBe(0)
        ->and($r['exonere'])->toBeTrue();
});

it('lève une erreur si le barème est introuvable', function () {
    $this->service->calculer('INDICE_400_799', 'EXT_ZONE_I', 'LOGE_SEUL', 2);
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

// ── Répartition avec avance ENGAGÉE (amendement après paiement de l'avance) ──
// Cf. docs/amendement-om.md. Base : 6 j × 45 000 = 270 000, avance figée 189 000.

it('répartit normalement (70/30) quand aucune avance n’est engagée', function () {
    definirPartAvance(null); // défaut 70 %

    expect($this->service->repartir(270000))->toBe([189000, 81000]);
});

it('fige l’avance et augmente le reliquat quand les jours augmentent', function () {
    // Rallonge : total 360 000, avance engagée 189 000 -> reliquat 171 000 (> 30 %).
    expect($this->service->repartir(360000, 189000))->toBe([189000, 171000]);
});

it('fige l’avance et réduit le reliquat quand les jours diminuent', function () {
    // Réduction : total 225 000, avance engagée 189 000 -> reliquat 36 000 (< 30 %).
    expect($this->service->repartir(225000, 189000))->toBe([189000, 36000]);
});

it('produit un reliquat NÉGATIF (trop-perçu) en forte réduction', function () {
    // Forte réduction : total 135 000 < avance 189 000 -> reliquat -54 000.
    expect($this->service->repartir(135000, 189000))->toBe([189000, -54000]);
});

it('ignore la part paramétrable une fois l’avance engagée', function () {
    // Même avec partAvance à 50 %, l'avance engagée prime : le reliquat s'ajuste.
    definirPartAvance(50);

    expect($this->service->repartir(360000, 189000))->toBe([189000, 171000]);

    definirPartAvance(null);
});
