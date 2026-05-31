<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Téléphone : national 10 chiffres ; opérateur Mobile Money du bénéficiaire.
        $agents = [
            // Bénéficiaire de la donnée de démonstration (OM réel réf. SEMO 0541405636).
            ['nom' => 'DADA', 'prenoms' => 'Richard', 'matricule' => 'ANT-1001', 'ifu' => '3201900000011', 'grade' => 'Directeur', 'indice' => 850, 'salaire_brut' => 410000, 'categorie' => 'ORDRE_1', 'telephone' => '0190000001', 'operateur' => 'MTN'],
            // CAV (conducteur de véhicule administratif) — bénéficiaire à part entière, payé selon SA catégorie.
            ['nom' => 'AKPACA', 'prenoms' => 'Gérard', 'matricule' => 'ANT-1002', 'ifu' => '3201900000022', 'grade' => 'Conducteur', 'indice' => 320, 'salaire_brut' => 150000, 'categorie' => 'ORDRE_3', 'telephone' => '0140000002', 'operateur' => 'MOOV'],

            // Quelques agents supplémentaires pour les écrans de liste / démonstration.
            ['nom' => 'ADJOVI', 'prenoms' => 'Sylvie', 'matricule' => 'ANT-1003', 'ifu' => '3201900000033', 'grade' => 'Chef de service', 'indice' => 620, 'salaire_brut' => 300000, 'categorie' => 'ORDRE_2', 'telephone' => '0196000003', 'operateur' => 'MTN'],
            ['nom' => 'TOSSOU', 'prenoms' => 'Marc', 'matricule' => 'ANT-1004', 'ifu' => '3201900000044', 'grade' => 'Cadre', 'indice' => 480, 'salaire_brut' => 220000, 'categorie' => 'ORDRE_2', 'telephone' => '0166000004', 'operateur' => 'MOOV'],
            ['nom' => 'AGOSSA', 'prenoms' => 'Fatima', 'matricule' => 'ANT-1005', 'ifu' => '3201900000055', 'grade' => 'Agent', 'indice' => 350, 'salaire_brut' => 160000, 'categorie' => 'ORDRE_3', 'telephone' => '0197000005', 'operateur' => 'MTN'],
            ['nom' => 'HOUNSA', 'prenoms' => 'Michel', 'matricule' => 'ANT-1006', 'ifu' => '3201900000066', 'grade' => 'Conducteur', 'indice' => 300, 'salaire_brut' => 140000, 'categorie' => 'ORDRE_3', 'telephone' => '0147000006', 'operateur' => 'MOOV'],
            ['nom' => 'GANDONOU', 'prenoms' => 'Estelle', 'matricule' => 'ANT-1007', 'ifu' => '3201900000077', 'grade' => 'Directeur', 'indice' => 900, 'salaire_brut' => 450000, 'categorie' => 'ORDRE_1', 'telephone' => '0198000007', 'operateur' => 'MTN'],
            ['nom' => 'SAOSSI', 'prenoms' => 'Paterne', 'matricule' => 'ANT-1008', 'ifu' => '3201900000088', 'grade' => 'Agent', 'indice' => 280, 'salaire_brut' => 130000, 'categorie' => 'ORDRE_3', 'telephone' => '0168000008', 'operateur' => 'MOOV', 'actif' => false],
        ];

        foreach ($agents as $data) {
            DB::table('agents')->updateOrInsert(
                ['matricule' => $data['matricule']],
                array_merge(
                    ['actif' => true],
                    $data,
                    ['created_at' => $now, 'updated_at' => $now]
                )
            );
        }
    }
}
