<?php

use App\Models\Bareme;
use Database\Seeders\BaremeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(BaremeSeeder::class));

dataset('bareme_officiel', [
    // INTERIEUR (grille « repas »)
    ['INDICE_SUP_800', 'INTERIEUR', 'UN_REPAS', 15000],
    ['INDICE_SUP_800', 'INTERIEUR', 'DEUX_REPAS', 30000],
    ['INDICE_SUP_800', 'INTERIEUR', 'JOURNEE_COMPLETE', 45000],
    ['INDICE_400_799', 'INTERIEUR', 'UN_REPAS', 10000],
    ['INDICE_400_799', 'INTERIEUR', 'DEUX_REPAS', 20000],
    ['INDICE_400_799', 'INTERIEUR', 'JOURNEE_COMPLETE', 35000],
    ['INDICE_INF_400', 'INTERIEUR', 'UN_REPAS', 8500],
    ['INDICE_INF_400', 'INTERIEUR', 'DEUX_REPAS', 15000],
    ['INDICE_INF_400', 'INTERIEUR', 'JOURNEE_COMPLETE', 30000],
    // EXT_ZONE_I (grille « hébergement »)
    ['INDICE_SUP_800', 'EXT_ZONE_I', 'JOURNEE_COMPLETE', 175000],
    ['INDICE_SUP_800', 'EXT_ZONE_I', 'LOGE_NOURRI', 60000],
    ['INDICE_SUP_800', 'EXT_ZONE_I', 'LOGE_SEUL', 90000],
    ['INDICE_SUP_800', 'EXT_ZONE_I', 'NOURRI_SEUL', 120000],
    ['INDICE_400_799', 'EXT_ZONE_I', 'JOURNEE_COMPLETE', 140000],
    ['INDICE_400_799', 'EXT_ZONE_I', 'LOGE_NOURRI', 50000],
    ['INDICE_400_799', 'EXT_ZONE_I', 'LOGE_SEUL', 70000],
    ['INDICE_400_799', 'EXT_ZONE_I', 'NOURRI_SEUL', 95000],
    ['INDICE_INF_400', 'EXT_ZONE_I', 'JOURNEE_COMPLETE', 105000],
    ['INDICE_INF_400', 'EXT_ZONE_I', 'LOGE_NOURRI', 35000],
    ['INDICE_INF_400', 'EXT_ZONE_I', 'LOGE_SEUL', 55000],
    ['INDICE_INF_400', 'EXT_ZONE_I', 'NOURRI_SEUL', 70000],
    // EXT_ZONE_II (= EXT_ZONE_III)
    ['INDICE_SUP_800', 'EXT_ZONE_II', 'JOURNEE_COMPLETE', 105000],
    ['INDICE_SUP_800', 'EXT_ZONE_II', 'LOGE_NOURRI', 35000],
    ['INDICE_SUP_800', 'EXT_ZONE_II', 'LOGE_SEUL', 55000],
    ['INDICE_SUP_800', 'EXT_ZONE_II', 'NOURRI_SEUL', 70000],
    ['INDICE_400_799', 'EXT_ZONE_II', 'JOURNEE_COMPLETE', 85000],
    ['INDICE_400_799', 'EXT_ZONE_II', 'LOGE_NOURRI', 30000],
    ['INDICE_400_799', 'EXT_ZONE_II', 'LOGE_SEUL', 45000],
    ['INDICE_400_799', 'EXT_ZONE_II', 'NOURRI_SEUL', 60000],
    ['INDICE_INF_400', 'EXT_ZONE_II', 'JOURNEE_COMPLETE', 65000],
    ['INDICE_INF_400', 'EXT_ZONE_II', 'LOGE_NOURRI', 25000],
    ['INDICE_INF_400', 'EXT_ZONE_II', 'LOGE_SEUL', 35000],
    ['INDICE_INF_400', 'EXT_ZONE_II', 'NOURRI_SEUL', 45000],
]);

it('respecte les valeurs exactes du barème officiel', function (string $cat, string $zone, string $type, int $taux) {
    expect(Bareme::taux($cat, $zone, $type))->toBe($taux);
})->with('bareme_officiel');

it('applique des taux identiques sur EXT_ZONE_II et EXT_ZONE_III', function () {
    foreach (['INDICE_SUP_800', 'INDICE_400_799', 'INDICE_INF_400'] as $cat) {
        foreach (['JOURNEE_COMPLETE', 'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL'] as $type) {
            expect(Bareme::taux($cat, 'EXT_ZONE_III', $type))
                ->toBe(Bareme::taux($cat, 'EXT_ZONE_II', $type));
        }
    }
});

it('contient exactement 45 lignes de barème', function () {
    expect(Bareme::count())->toBe(45);
});
