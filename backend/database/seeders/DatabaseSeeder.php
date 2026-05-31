<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,        // comptes applicatifs (login)
            ZoneSeeder::class,        // 4 zones de référence
            BaremeSeeder::class,      // barème officiel ANaTT
            AgentSeeder::class,       // référentiel bénéficiaires (dont CAV)
            MissionSeeder::class,     // mission de démonstration (BOHICON)
            OrdreMissionSeeder::class, // OM participant + OM CAV
            AuditLogSeeder::class,    // journal d'activité
        ]);
    }
}
