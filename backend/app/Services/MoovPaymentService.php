<?php

namespace App\Services;

use App\Models\OrdreMission;
use App\Models\Paiement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MoovPaymentService
{
    /**
     * Format fichier virement Moov (CSV):
     * NUMERO_TELEPHONE;MONTANT;REFERENCE;NOM_BENEFICIAIRE;MOTIF
     */
    public function genererFichier(Collection $ordres): array
    {
        $lignes = [];
        $paiements = [];

        $lignes[] = 'NUMERO_TELEPHONE;MONTANT;REFERENCE;NOM_BENEFICIAIRE;MOTIF';

        foreach ($ordres as $ordre) {
            $agent = $ordre->agent;

            if (! $agent->numero_moov) {
                continue;
            }

            $motif = "Per diem mission {$ordre->destination} {$ordre->date_depart->format('M Y')}";

            $lignes[] = implode(';', [
                $agent->numero_moov,
                (int) $ordre->montant_per_diem,
                $ordre->numero,
                "{$agent->nom} {$agent->prenom}",
                $motif,
            ]);

            $paiement = Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $agent->id,
                'operateur' => 'MOOV',
                'numero_destinataire' => $agent->numero_moov,
                'montant' => $ordre->montant_per_diem,
                'statut' => 'EN_ATTENTE',
            ]);

            $paiements[] = $paiement;
        }

        $filename = 'paiements/MOOV_' . now()->format('Ymd_His') . '.csv';
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
            $statut = $colonnes[5] ?? 'ECHEC';

            if ($reference) {
                $paiement = Paiement::whereHas('ordreMission', function ($q) use ($reference) {
                    $q->where('numero', $reference);
                })->where('operateur', 'MOOV')->first();

                if ($paiement) {
                    $paiement->update([
                        'statut' => strtoupper($statut) === 'SUCCESS' ? 'SUCCES' : 'ECHEC',
                        'reference_transaction' => $colonnes[6] ?? null,
                        'date_virement' => now(),
                    ]);
                    $resultats[strtoupper($statut) === 'SUCCESS' ? 'succes' : 'echec']++;
                }
            }
        }

        return $resultats;
    }
}
