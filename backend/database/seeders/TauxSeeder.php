<?php

namespace Database\Seeders;

use App\Models\TauxPerDiem;
use Illuminate\Database\Seeder;

class TauxSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['A1', 'A2', 'B', 'C', 'D'];
        $destinations = [
            'LOCALE' => [25000, 20000, 15000, 12000, 10000],
            'REGIONALE' => [50000, 40000, 30000, 25000, 20000],
            'INTERNATIONALE' => [100000, 80000, 60000, 50000, 40000],
        ];

        foreach ($destinations as $type => $montants) {
            foreach ($categories as $i => $cat) {
                TauxPerDiem::create([
                    'categorie' => $cat,
                    'destination_type' => $type,
                    'montant_journalier' => $montants[$i],
                    'annee' => 2026,
                    'actif' => true,
                ]);
            }
        }

        // Taux annee precedente (inactifs)
        foreach (['A1' => 22000, 'A2' => 18000, 'B' => 13000] as $cat => $montant) {
            TauxPerDiem::create([
                'categorie' => $cat,
                'destination_type' => 'LOCALE',
                'montant_journalier' => $montant,
                'annee' => 2025,
                'actif' => false,
            ]);
        }
    }
}
