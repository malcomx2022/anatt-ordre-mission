<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\TauxPerDiem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Utilisateurs
        $admin = User::factory()->create([
            'name' => 'Gislain AHOUANSOU',
            'email' => 'admin@anatt.bj',
            'role' => 'ADMIN',
        ]);

        $chef = User::factory()->create([
            'name' => 'Marie DOSSOU',
            'email' => 'chef@anatt.bj',
            'role' => 'CHEF_SERVICE',
        ]);

        $directeur = User::factory()->create([
            'name' => 'Pierre HOUNGBO',
            'email' => 'directeur@anatt.bj',
            'role' => 'DIRECTEUR',
        ]);

        $drh = User::factory()->create([
            'name' => 'Amina BELLO',
            'email' => 'drh@anatt.bj',
            'role' => 'DRH',
        ]);

        // Agents
        $agents = [
            ['matricule' => 'ANT-001', 'nom' => 'KOUASSI', 'prenom' => 'Jean', 'email' => 'j.kouassi@anatt.bj', 'telephone' => '22997123456', 'numero_moov' => '22997123456', 'service' => 'Direction Technique', 'categorie' => 'A1'],
            ['matricule' => 'ANT-002', 'nom' => 'ADJOVI', 'prenom' => 'Sylvie', 'email' => 's.adjovi@anatt.bj', 'telephone' => '22961234567', 'numero_mtn' => '22961234567', 'service' => 'Direction Administrative', 'categorie' => 'A2'],
            ['matricule' => 'ANT-003', 'nom' => 'TOSSOU', 'prenom' => 'Marc', 'email' => 'm.tossou@anatt.bj', 'telephone' => '22996789012', 'numero_moov' => '22996789012', 'numero_mtn' => '22966789012', 'service' => 'Service Informatique', 'categorie' => 'B'],
            ['matricule' => 'ANT-004', 'nom' => 'AGOSSA', 'prenom' => 'Fatima', 'email' => 'f.agossa@anatt.bj', 'telephone' => '22998765432', 'numero_moov' => '22998765432', 'service' => 'Comptabilite', 'categorie' => 'C'],
            ['matricule' => 'ANT-005', 'nom' => 'KPADE', 'prenom' => 'Olivier', 'email' => 'o.kpade@anatt.bj', 'telephone' => '22962345678', 'numero_mtn' => '22962345678', 'service' => 'Direction Technique', 'categorie' => 'B'],
        ];

        foreach ($agents as $agentData) {
            Agent::create($agentData);
        }

        // Taux per diem
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
    }
}
