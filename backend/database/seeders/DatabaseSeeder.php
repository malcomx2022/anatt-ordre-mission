<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Socle ESSENTIEL (toujours seedé, y compris en production) ──
        $this->call([
            UserSeeder::class,        // super admin initial (+ comptes démo en local/testing seulement)
            ZoneSeeder::class,        // 4 zones de référence
            BaremeSeeder::class,      // barème officiel ANaTT
        ]);

        // ── Données de DÉMONSTRATION (jamais en production) ──
        // Agents fictifs, missions/OM et paiements confirmés factices, journal d'audit.
        // S'appuient sur les comptes de démo (gestion@, finance@, controle@…).
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            AgentSeeder::class,       // référentiel bénéficiaires (dont CAV)
            MissionSeeder::class,     // mission de démonstration (BOHICON, à traiter)
            OrdreMissionSeeder::class, // OM participant + OM CAV (statut IMPORTE)
            PaiementDemoSeeder::class, // missions soldées + versements CONFIRMÉS (mois courant)
            AuditLogSeeder::class,    // journal d'activité
        ]);
    }
}
