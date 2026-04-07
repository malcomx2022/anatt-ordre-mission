<?php

namespace Database\Seeders;

use App\Models\OrdreMission;
use App\Models\Paiement;
use Illuminate\Database\Seeder;

class PaiementSeeder extends Seeder
{
    public function run(): void
    {
        $paidMissions = OrdreMission::where('statut', 'PAYE')->with('agent')->get();

        foreach ($paidMissions as $ordre) {
            $agent = $ordre->agent;
            $operateur = $agent->numero_mtn ? 'MTN' : 'MOOV';
            $numero = $operateur === 'MTN' ? $agent->numero_mtn : $agent->numero_moov;

            if (!$numero) continue;

            // Avance (33%)
            Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $agent->id,
                'operateur' => $operateur,
                'numero_destinataire' => $numero,
                'montant' => round($ordre->montant_per_diem * 0.33),
                'statut' => 'SUCCES',
                'reference_transaction' => 'TXN-' . strtoupper(substr(md5($ordre->numero . 'avance'), 0, 7)),
                'fichier_virement' => "paiements/{$operateur}_" . now()->format('Ymd') . '_001.csv',
                'date_virement' => $ordre->date_depart,
            ]);

            // Reliquat (67%)
            Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $agent->id,
                'operateur' => $operateur,
                'numero_destinataire' => $numero,
                'montant' => $ordre->montant_per_diem - round($ordre->montant_per_diem * 0.33),
                'statut' => 'SUCCES',
                'reference_transaction' => 'TXN-' . strtoupper(substr(md5($ordre->numero . 'reliquat'), 0, 7)),
                'fichier_virement' => "paiements/{$operateur}_" . now()->format('Ymd') . '_002.csv',
                'date_virement' => $ordre->date_retour,
            ]);
        }

        // Paiements en attente pour missions approuvees
        $approvedMissions = OrdreMission::where('statut', 'APPROUVE_DG')->with('agent')->get();
        foreach ($approvedMissions as $ordre) {
            $agent = $ordre->agent;
            $operateur = $agent->numero_moov ? 'MOOV' : 'MTN';
            $numero = $operateur === 'MOOV' ? $agent->numero_moov : $agent->numero_mtn;

            if (!$numero) continue;

            Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $agent->id,
                'operateur' => $operateur,
                'numero_destinataire' => $numero,
                'montant' => round($ordre->montant_per_diem * 0.33),
                'statut' => 'EN_ATTENTE',
            ]);
        }

        // Un paiement echoue pour la demo
        $firstPaid = $paidMissions->first();
        if ($firstPaid) {
            Paiement::create([
                'ordre_mission_id' => $firstPaid->id,
                'agent_id' => $firstPaid->agent_id,
                'operateur' => 'MTN',
                'numero_destinataire' => '22960000000',
                'montant' => 15000,
                'statut' => 'ECHEC',
                'reference_transaction' => 'ERR-TIMEOUT-001',
                'date_virement' => now()->subDays(3),
            ]);
        }
    }
}
