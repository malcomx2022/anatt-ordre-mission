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
            ['INDICE_SUP_800', 'INTERIEUR', 'UN_REPAS', 15000],
            ['INDICE_SUP_800', 'INTERIEUR', 'DEUX_REPAS', 30000],
            ['INDICE_SUP_800', 'INTERIEUR', 'JOURNEE_COMPLETE', 45000],
            ['INDICE_400_799', 'INTERIEUR', 'UN_REPAS', 10000],
            ['INDICE_400_799', 'INTERIEUR', 'DEUX_REPAS', 20000],
            ['INDICE_400_799', 'INTERIEUR', 'JOURNEE_COMPLETE', 35000],
            ['INDICE_INF_400', 'INTERIEUR', 'UN_REPAS', 8500],
            ['INDICE_INF_400', 'INTERIEUR', 'DEUX_REPAS', 15000],
            ['INDICE_INF_400', 'INTERIEUR', 'JOURNEE_COMPLETE', 30000],

            // ---- EXT_ZONE_I (grille « hébergement ») ----
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
        ];

        // ---- EXT_ZONE_II et EXT_ZONE_III (valeurs identiques) ----
        $extIIetIII = [
            ['INDICE_SUP_800', 'JOURNEE_COMPLETE', 105000],
            ['INDICE_SUP_800', 'LOGE_NOURRI', 35000],
            ['INDICE_SUP_800', 'LOGE_SEUL', 55000],
            ['INDICE_SUP_800', 'NOURRI_SEUL', 70000],
            ['INDICE_400_799', 'JOURNEE_COMPLETE', 85000],
            ['INDICE_400_799', 'LOGE_NOURRI', 30000],
            ['INDICE_400_799', 'LOGE_SEUL', 45000],
            ['INDICE_400_799', 'NOURRI_SEUL', 60000],
            ['INDICE_INF_400', 'JOURNEE_COMPLETE', 65000],
            ['INDICE_INF_400', 'LOGE_NOURRI', 25000],
            ['INDICE_INF_400', 'LOGE_SEUL', 35000],
            ['INDICE_INF_400', 'NOURRI_SEUL', 45000],
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
