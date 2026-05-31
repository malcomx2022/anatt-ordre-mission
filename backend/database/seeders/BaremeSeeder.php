<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BaremeSeeder extends Seeder
{
    /**
     * BARÈME OFFICIEL ANaTT — montants exacts (FCFA), à insérer tels quels.
     *
     * Contrainte applicative respectée :
     *   - UN_REPAS / DEUX_REPAS  => uniquement zone INTERIEUR
     *   - LOGE_* / NOURRI_SEUL   => uniquement zones EXTERIEUR
     *   - JOURNEE_COMPLETE       => toutes zones
     *   - EXT_ZONE_II et EXT_ZONE_III partagent les mêmes valeurs.
     */
    public function run(): void
    {
        $now = now();

        $baremes = [
            // ---- INTERIEUR (grille « repas ») ----
            ['ORDRE_1', 'INTERIEUR', 'UN_REPAS', 15000],
            ['ORDRE_1', 'INTERIEUR', 'DEUX_REPAS', 30000],
            ['ORDRE_1', 'INTERIEUR', 'JOURNEE_COMPLETE', 45000],
            ['ORDRE_2', 'INTERIEUR', 'UN_REPAS', 10000],
            ['ORDRE_2', 'INTERIEUR', 'DEUX_REPAS', 20000],
            ['ORDRE_2', 'INTERIEUR', 'JOURNEE_COMPLETE', 35000],
            ['ORDRE_3', 'INTERIEUR', 'UN_REPAS', 8500],
            ['ORDRE_3', 'INTERIEUR', 'DEUX_REPAS', 15000],
            ['ORDRE_3', 'INTERIEUR', 'JOURNEE_COMPLETE', 30000],

            // ---- EXT_ZONE_I (grille « hébergement ») ----
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
        ];

        // ---- EXT_ZONE_II et EXT_ZONE_III (valeurs identiques) ----
        $extIIetIII = [
            ['ORDRE_1', 'JOURNEE_COMPLETE', 105000],
            ['ORDRE_1', 'LOGE_NOURRI', 35000],
            ['ORDRE_1', 'LOGE_SEUL', 55000],
            ['ORDRE_1', 'NOURRI_SEUL', 70000],
            ['ORDRE_2', 'JOURNEE_COMPLETE', 85000],
            ['ORDRE_2', 'LOGE_NOURRI', 30000],
            ['ORDRE_2', 'LOGE_SEUL', 45000],
            ['ORDRE_2', 'NOURRI_SEUL', 60000],
            ['ORDRE_3', 'JOURNEE_COMPLETE', 65000],
            ['ORDRE_3', 'LOGE_NOURRI', 25000],
            ['ORDRE_3', 'LOGE_SEUL', 35000],
            ['ORDRE_3', 'NOURRI_SEUL', 45000],
        ];

        foreach (['EXT_ZONE_II', 'EXT_ZONE_III'] as $zone) {
            foreach ($extIIetIII as [$cat, $type, $taux]) {
                $baremes[] = [$cat, $zone, $type, $taux];
            }
        }

        foreach ($baremes as [$categorie, $zone, $type, $taux]) {
            DB::table('baremes')->updateOrInsert(
                [
                    'categorie' => $categorie,
                    'zone_code' => $zone,
                    'type_prise_en_charge' => $type,
                ],
                [
                    'taux_journalier' => $taux,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
