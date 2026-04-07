<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AgentSeeder::class,
            TauxSeeder::class,
            OrdreMissionSeeder::class,
            PaiementSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
