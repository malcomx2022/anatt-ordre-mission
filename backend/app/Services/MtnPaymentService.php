<?php

namespace App\Services;

use App\Models\OrdreMission;
use App\Models\Paiement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MtnPaymentService
{
    /**
     * Format fichier virement MTN (CSV):
     * MSISDN;AMOUNT;EXTERNAL_REF;BENEFICIARY_NAME;NARRATION
     */
    public function genererFichier(Collection $ordres): array
    {
        $lignes = [];
        $paiements = [];

        $lignes[] = 'MSISDN;AMOUNT;EXTERNAL_REF;BENEFICIARY_NAME;NARRATION';

        foreach ($ordres as $ordre) {
            $agent = $ordre->agent;

            if (! $agent->numero_mtn) {
                continue;
            }

            $lignes[] = implode(';', [
                $agent->numero_mtn,
                (int) $ordre->montant_per_diem,
                $ordre->numero,
                "{$agent->nom} {$agent->prenom}",
                'Per diem mission',
            ]);

            $paiement = Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $agent->id,
                'operateur' => 'MTN',
                'numero_destinataire' => $agent->numero_mtn,
                'montant' => $ordre->montant_per_diem,
                'statut' => 'EN_ATTENTE',
            ]);

            $paiements[] = $paiement;
        }

        $filename = 'paiements/MTN_' . now()->format('Ymd_His') . '.csv';
        Storage::disk('local')->put($filename, implode("\n", $lignes));

        foreach ($paiements as $paiement) {
            $paiement->update(['fichier_virement' => $filename]);
        }

        return [
            'fichier' => $filename,
            'nb_paiements' => count($paiements),
            'montant_total' => collect($paiements)->sum('montant'),
            'paiements' => $paiements,
        ];
    }

    public function traiterRetour(string $contenuCsv): array
    {
        $lignes = explode("\n", trim($contenuCsv));
        $resultats = ['succes' => 0, 'echec' => 0];

        foreach (array_slice($lignes, 1) as $ligne) {
            $colonnes = explode(';', $ligne);
            if (count($colonnes) < 3) {
                continue;
            }

            $reference = $colonnes[2] ?? null;
            $statut = $colonnes[5] ?? 'FAILED';

            if ($reference) {
                $paiement = Paiement::whereHas('ordreMission', function ($q) use ($reference) {
                    $q->where('numero', $reference);
                })->where('operateur', 'MTN')->first();

                if ($paiement) {
                    $paiement->update([
                        'statut' => strtoupper($statut) === 'SUCCESSFUL' ? 'SUCCES' : 'ECHEC',
                        'reference_transaction' => $colonnes[6] ?? null,
                        'date_virement' => now(),
                    ]);
                    $resultats[strtoupper($statut) === 'SUCCESSFUL' ? 'succes' : 'echec']++;
                }
            }
        }

        return $resultats;
    }
}
