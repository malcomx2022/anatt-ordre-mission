<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $chef = User::where('email', 'chef@anatt.bj')->first();
        $agentUser = User::where('email', 'agent@anatt.bj')->first();

        $agents = [
            ['user_id' => $agentUser?->id, 'matricule' => 'ANT-001', 'nom' => 'KOUASSI', 'prenom' => 'Jean', 'email' => 'j.kouassi@anatt.bj', 'telephone' => '22997123456', 'numero_moov' => '22997123456', 'numero_mtn' => '22967123456', 'service' => 'Direction Technique', 'categorie' => 'A1'],
            ['matricule' => 'ANT-002', 'nom' => 'ADJOVI', 'prenom' => 'Sylvie', 'email' => 's.adjovi@anatt.bj', 'telephone' => '22961234567', 'numero_mtn' => '22961234567', 'service' => 'Direction Administrative', 'categorie' => 'A2'],
            ['user_id' => $chef?->id, 'matricule' => 'ANT-003', 'nom' => 'DOSSOU', 'prenom' => 'Marie', 'email' => 'm.dossou@anatt.bj', 'telephone' => '22996789012', 'numero_moov' => '22996789012', 'service' => 'Direction Technique', 'categorie' => 'A1'],
            ['matricule' => 'ANT-004', 'nom' => 'TOSSOU', 'prenom' => 'Marc', 'email' => 'm.tossou@anatt.bj', 'telephone' => '22995678901', 'numero_moov' => '22995678901', 'numero_mtn' => '22965678901', 'service' => 'Service Informatique', 'categorie' => 'B'],
            ['matricule' => 'ANT-005', 'nom' => 'AGOSSA', 'prenom' => 'Fatima', 'email' => 'f.agossa@anatt.bj', 'telephone' => '22998765432', 'numero_moov' => '22998765432', 'service' => 'Comptabilite', 'categorie' => 'C'],
            ['matricule' => 'ANT-006', 'nom' => 'KPADE', 'prenom' => 'Olivier', 'email' => 'o.kpade@anatt.bj', 'telephone' => '22962345678', 'numero_mtn' => '22962345678', 'service' => 'Direction Technique', 'categorie' => 'B'],
            ['matricule' => 'ANT-007', 'nom' => 'HOUNSA', 'prenom' => 'Michel', 'email' => 'm.hounsa@anatt.bj', 'telephone' => '22993456789', 'numero_moov' => '22993456789', 'numero_mtn' => '22963456789', 'service' => 'Direction Administrative', 'categorie' => 'C'],
            ['matricule' => 'ANT-008', 'nom' => 'BOKO', 'prenom' => 'Grace', 'email' => 'g.boko@anatt.bj', 'telephone' => '22994567890', 'numero_mtn' => '22964567890', 'service' => 'Service Informatique', 'categorie' => 'D'],
            ['matricule' => 'ANT-009', 'nom' => 'GANDONOU', 'prenom' => 'Estelle', 'email' => 'e.gandonou@anatt.bj', 'telephone' => '22997890123', 'numero_moov' => '22997890123', 'service' => 'Comptabilite', 'categorie' => 'C'],
            ['matricule' => 'ANT-010', 'nom' => 'SAOSSI', 'prenom' => 'Paterne', 'email' => 'p.saossi@anatt.bj', 'telephone' => '22968901234', 'numero_mtn' => '22968901234', 'service' => 'Direction Technique', 'categorie' => 'A2', 'actif' => false],
        ];

        foreach ($agents as $data) {
            Agent::create($data);
        }
    }
}
