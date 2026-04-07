<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrdreMissionSeeder extends Seeder
{
    public function run(): void
    {
        $agents = Agent::where('actif', true)->get();
        $chef = User::where('role', 'CHEF_SERVICE')->first();
        $dg = User::where('role', 'DIRECTEUR')->first();

        $missions = [
            ['agent' => 'ANT-001', 'objet' => 'Inspection technique gares routieres', 'destination' => 'Parakou', 'depart' => '2026-03-10', 'retour' => '2026-03-14', 'statut' => 'PAYE', 'type' => 'LOCALE'],
            ['agent' => 'ANT-002', 'objet' => 'Formation gestion administrative', 'destination' => 'Porto-Novo', 'depart' => '2026-03-15', 'retour' => '2026-03-17', 'statut' => 'APPROUVE_DG', 'type' => 'LOCALE'],
            ['agent' => 'ANT-004', 'objet' => 'Audit comptable regional', 'destination' => 'Abomey', 'depart' => '2026-03-20', 'retour' => '2026-03-22', 'statut' => 'VALIDE_N1', 'type' => 'LOCALE'],
            ['agent' => 'ANT-006', 'objet' => 'Controle technique vehicules', 'destination' => 'Natitingou', 'depart' => '2026-04-01', 'retour' => '2026-04-05', 'statut' => 'SOUMIS', 'type' => 'REGIONALE'],
            ['agent' => 'ANT-001', 'objet' => 'Seminaire transport urbain CEDEAO', 'destination' => 'Lome', 'depart' => '2026-04-10', 'retour' => '2026-04-14', 'statut' => 'SOUMIS', 'type' => 'INTERNATIONALE'],
            ['agent' => 'ANT-007', 'objet' => 'Inventaire materiel bureaux regionaux', 'destination' => 'Lokossa', 'depart' => '2026-04-08', 'retour' => '2026-04-10', 'statut' => 'BROUILLON', 'type' => 'LOCALE'],
            ['agent' => 'ANT-004', 'objet' => 'Formation logiciel comptable', 'destination' => 'Cotonou', 'depart' => '2026-04-12', 'retour' => '2026-04-12', 'statut' => 'BROUILLON', 'type' => 'LOCALE'],
            ['agent' => 'ANT-005', 'objet' => 'Verification balances comptables', 'destination' => 'Parakou', 'depart' => '2026-03-05', 'retour' => '2026-03-08', 'statut' => 'REJETE', 'type' => 'LOCALE'],
            ['agent' => 'ANT-009', 'objet' => 'Mission controle budgetaire', 'destination' => 'Djougou', 'depart' => '2026-03-25', 'retour' => '2026-03-28', 'statut' => 'PAYE', 'type' => 'REGIONALE'],
            ['agent' => 'ANT-006', 'objet' => 'Reunion technique interministerielle', 'destination' => 'Porto-Novo', 'depart' => '2026-02-15', 'retour' => '2026-02-16', 'statut' => 'PAYE', 'type' => 'LOCALE'],
            ['agent' => 'ANT-008', 'objet' => 'Installation reseau bureau Bohicon', 'destination' => 'Bohicon', 'depart' => '2026-04-14', 'retour' => '2026-04-16', 'statut' => 'SOUMIS', 'type' => 'LOCALE'],
            ['agent' => 'ANT-002', 'objet' => 'Conference transport durable', 'destination' => 'Accra', 'depart' => '2026-04-20', 'retour' => '2026-04-24', 'statut' => 'BROUILLON', 'type' => 'INTERNATIONALE'],
        ];

        $numero = 1;
        foreach ($missions as $m) {
            $agent = $agents->firstWhere('matricule', $m['agent']);
            if (!$agent) continue;

            $taux = TauxPerDiem::where('categorie', $agent->categorie)
                ->where('destination_type', $m['type'])
                ->where('annee', 2026)
                ->where('actif', true)
                ->first();

            if (!$taux) continue;

            $nbJours = now()->parse($m['retour'])->diffInDays(now()->parse($m['depart'])) + 1;
            $montant = $nbJours * $taux->montant_journalier;

            $data = [
                'numero' => sprintf('OM-2026-%04d', $numero++),
                'agent_id' => $agent->id,
                'objet' => $m['objet'],
                'destination' => $m['destination'],
                'date_depart' => $m['depart'],
                'date_retour' => $m['retour'],
                'nb_jours' => $nbJours,
                'taux_id' => $taux->id,
                'montant_per_diem' => $montant,
                'statut' => $m['statut'],
            ];

            if (in_array($m['statut'], ['VALIDE_N1', 'APPROUVE_DG', 'PAYE'])) {
                $data['valide_par_chef'] = $chef?->id;
            }
            if (in_array($m['statut'], ['APPROUVE_DG', 'PAYE'])) {
                $data['valide_par_dg'] = $dg?->id;
            }
            if ($m['statut'] === 'REJETE') {
                $data['commentaire_rejet'] = 'Budget insuffisant pour cette periode. Merci de reporter la mission au mois suivant.';
            }

            OrdreMission::create($data);
        }
    }
}
