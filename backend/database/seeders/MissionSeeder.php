<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Mission de démonstration : destination BOHICON (zone INTERIEUR),
        // du 12/11/2025 au 14/11/2025. Regroupe l'OM participant + l'OM du CAV.
        DB::table('missions')->updateOrInsert(
            ['objet' => 'Supervision des opérations de contrôle technique', 'destination' => 'BOHICON'],
            [
                'zone_code' => 'INTERIEUR',
                'date_depart' => '2025-11-12',
                'date_retour' => '2025-11-14',
                'imputation_budgetaire' => 'ANATT-2025-DT-014',
                'prise_en_charge_tiers' => false,
                'observations' => 'Mission de démonstration retranscrite depuis SEMO (réf. OM 0541405636 du 11/11/2025).',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
