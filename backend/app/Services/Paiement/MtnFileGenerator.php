<?php

namespace App\Services\Paiement;

use App\Models\Mission;
use App\Models\Versement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Générateur du fichier de paiement MTN (.txt) — format exact validé sur fichier réel.
 *
 *  - Texte ASCII, séparateur VIRGULE, fins de ligne CRLF (\r\n), saut de ligne final.
 *  - Ligne 1 (en-tête) : HDR,{COMPTE},{NB_LIGNES},{MONTANT_TOTAL}
 *  - Lignes détail (seq 1..N) : {seq},MSISDN,{msisdn},{montant},{libelle}
 *
 * Le périmètre, le lot (GENERE) et le passage des versements à GENERE sont gérés
 * par la classe de base FileGenerator (flux transactionnel commun MTN/Moov).
 */
class MtnFileGenerator extends FileGenerator
{
    protected function operateur(): string
    {
        return 'MTN';
    }

    /**
     * @param  Collection<int,Versement>  $versements
     * @return array{chemin:string, nom_fichier:string, nb_lignes:int, montant_total:int}
     */
    protected function construireFichier(Mission $mission, string $tranche, Collection $versements): array
    {
        $compte = config('paiement.mtn.compte');
        $theme = $mission->objet;

        $details = [];
        $sommeDetail = 0;
        $seq = 0;

        foreach ($versements as $versement) {
            $agent = $versement->ordreMission->agent;
            $montant = (int) $versement->montant;
            $sommeDetail += $montant;
            $seq++;

            $details[] = implode(',', [
                $seq,
                'MSISDN',
                $this->msisdn($versement->telephone),
                $montant,
                $this->libelle($agent->nom, $agent->prenoms, $theme),
            ]);
        }

        $nbLignes = count($details);
        $montantTotal = (int) $versements->sum('montant');

        // Contrôle d'intégrité : en-tête (NB_LIGNES, MONTANT_TOTAL) == détail recalculé.
        if ($nbLignes !== $seq || $montantTotal !== $sommeDetail) {
            throw new RuntimeException(
                "Incohérence en-tête/détail MTN : NB_LIGNES {$nbLignes} vs {$seq}, MONTANT_TOTAL {$montantTotal} vs {$sommeDetail}.",
            );
        }

        $entete = implode(',', ['HDR', $compte, $nbLignes, $montantTotal]);

        // CRLF entre lignes + saut de ligne final.
        $contenu = implode("\r\n", array_merge([$entete], $details))."\r\n";

        $nomFichier = $this->nomFichier($theme, $tranche, 'txt');
        // Un dossier par mission : deux missions de même objet produisent le même
        // nom de fichier, qui ne doit pas écraser le fichier de l'autre lot.
        $chemin = "paiements/{$mission->id}/{$nomFichier}";
        Storage::disk('local')->put($chemin, $contenu);

        return [
            'chemin' => $chemin,
            'nom_fichier' => $nomFichier,
            'nb_lignes' => $nbLignes,
            'montant_total' => $montantTotal,
        ];
    }
}
