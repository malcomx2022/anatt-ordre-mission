<?php

use App\Models\Bareme;
use Database\Seeders\BaremeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(BaremeSeeder::class));

dataset('bareme_officiel', [
    // INTERIEUR (grille « repas »)
    ['ORDRE_1', 'INTERIEUR', 'UN_REPAS', 15000],
    ['ORDRE_1', 'INTERIEUR', 'DEUX_REPAS', 30000],
    ['ORDRE_1', 'INTERIEUR', 'JOURNEE_COMPLETE', 45000],
    ['ORDRE_2', 'INTERIEUR', 'UN_REPAS', 10000],
    ['ORDRE_2', 'INTERIEUR', 'DEUX_REPAS', 20000],
    ['ORDRE_2', 'INTERIEUR', 'JOURNEE_COMPLETE', 35000],
    ['ORDRE_3', 'INTERIEUR', 'UN_REPAS', 8500],
    ['ORDRE_3', 'INTERIEUR', 'DEUX_REPAS', 15000],
    ['ORDRE_3', 'INTERIEUR', 'JOURNEE_COMPLETE', 30000],
    // EXT_ZONE_I (grille « hébergement »)
    ['ORDRE_1', 'EXT_ZONE_I', 'JOURNEE_COMPLETE', 175000],
    ['ORDRE_1', 'EXT_ZONE_I', 'LOGE_NOURRI', 60000],
    ['ORDRE_1', 'EXT_ZONE_I', 'LOGE_SEUL', 90000],
    ['ORDRE_1', 'EXT_ZONE_I', 'NOURRI_SEUL', 120000],
    ['ORDRE_2', 'EXT_ZONE_I', 'JOURNEE_COMPLETE', 140000],
    ['ORDRE_2', 'EXT_ZONE_I', 'LOGE_NOURRI', 50000],
    ['ORDRE_2', 'EXT_ZONE_I', 'LOGE_SEUL', 70000],
    ['ORDRE_2', 'EXT_ZONE_I', 'NOURRI_SEUL', 95000],
    ['ORDRE_3', 'EXT_ZONE_I', 'JOURNEE_COMPLETE', 105000],
    ['ORDRE_3', 'EXT_ZONE_I', 'LOGE_NOURRI', 35000],
    ['ORDRE_3', 'EXT_ZONE_I', 'LOGE_SEUL', 55000],
    ['ORDRE_3', 'EXT_ZONE_I', 'NOURRI_SEUL', 70000],
    // EXT_ZONE_II (= EXT_ZONE_III)
    ['ORDRE_1', 'EXT_ZONE_II', 'JOURNEE_COMPLETE', 105000],
    ['ORDRE_1', 'EXT_ZONE_II', 'LOGE_NOURRI', 35000],
    ['ORDRE_1', 'EXT_ZONE_II', 'LOGE_SEUL', 55000],
    ['ORDRE_1', 'EXT_ZONE_II', 'NOURRI_SEUL', 70000],
    ['ORDRE_2', 'EXT_ZONE_II', 'JOURNEE_COMPLETE', 85000],
    ['ORDRE_2', 'EXT_ZONE_II', 'LOGE_NOURRI', 30000],
    ['ORDRE_2', 'EXT_ZONE_II', 'LOGE_SEUL', 45000],
    ['ORDRE_2', 'EXT_ZONE_II', 'NOURRI_SEUL', 60000],
    ['ORDRE_3', 'EXT_ZONE_II', 'JOURNEE_COMPLETE', 65000],
    ['ORDRE_3', 'EXT_ZONE_II', 'LOGE_NOURRI', 25000],
    ['ORDRE_3', 'EXT_ZONE_II', 'LOGE_SEUL', 35000],
    ['ORDRE_3', 'EXT_ZONE_II', 'NOURRI_SEUL', 45000],
]);

it('respecte les valeurs exactes du barème officiel', function (string $cat, string $zone, string $type, int $taux) {
    expect(Bareme::taux($cat, $zone, $type))->toBe($taux);
})->with('bareme_officiel');

it('applique des taux identiques sur EXT_ZONE_II et EXT_ZONE_III', function () {
    foreach (['ORDRE_1', 'ORDRE_2', 'ORDRE_3'] as $cat) {
        foreach (['JOURNEE_COMPLETE', 'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL'] as $type) {
            expect(Bareme::taux($cat, 'EXT_ZONE_III', $type))
                ->toBe(Bareme::taux($cat, 'EXT_ZONE_II', $type));
        }
    }
});

it('contient exactement 45 lignes de barème', function () {
    expect(Bareme::count())->toBe(45);
});
