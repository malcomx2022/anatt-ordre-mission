<?php

namespace App\Services\Paiement;

use App\Models\Mission;
use App\Models\Versement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Générateur du fichier de paiement Moov (.xlsx) — format exact validé sur fichier réel.
 *
 *  - Feuille « Sheet1 ».
 *  - Ligne 1 : en-tête FIXE de 9 colonnes (ordre exact).
 *  - Lignes 2..N+1 : A="MSISDN", B=229+tel EN TEXTE, C-F vides, G=montant (entier),
 *    H=libellé normalisé, I=vide.
 *
 * Périmètre, lot (GENERE), statuts versements et idempotence : classe de base FileGenerator.
 */
class MoovFileGenerator extends FileGenerator
{
    private const ENTETE = [
        'Receiver Identifier Type',
        'IdentifierValue',
        'Validation KYC',
        'Validation KYC value',
        'PayerAccountName',
        'AccountName',
        'Amount',
        'Comment',
        'VoucherSecretCode',
    ];

    protected function operateur(): string
    {
        return 'MOOV';
    }

    /**
     * @param  Collection<int,Versement>  $versements
     * @return array{chemin:string, nom_fichier:string, nb_lignes:int, montant_total:int}
     */
    protected function construireFichier(Mission $mission, string $tranche, Collection $versements): array
    {
        $theme = $mission->objet;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(config('paiement.moov.sheet', 'Sheet1'));

        // Ligne 1 : en-tête fixe.
        foreach (self::ENTETE as $i => $titre) {
            $sheet->setCellValueExplicit([$i + 1, 1], $titre, DataType::TYPE_STRING);
        }

        $ligne = 2;
        $montantTotal = 0;

        foreach ($versements as $versement) {
            $agent = $versement->ordreMission->agent;
            $montant = (int) $versement->montant;
            $montantTotal += $montant;

            $sheet->setCellValueExplicit([1, $ligne], 'MSISDN', DataType::TYPE_STRING);            // A
            // B : numéro EN TEXTE pour éviter la notation scientifique / perte du 0.
            $sheet->setCellValueExplicit([2, $ligne], $this->msisdn($versement->telephone), DataType::TYPE_STRING);
            // C, D, E, F : vides.
            $sheet->setCellValue([7, $ligne], $montant);                                            // G (entier numérique)
            $sheet->setCellValueExplicit([8, $ligne], $this->libelle($agent->nom, $agent->prenoms, $theme), DataType::TYPE_STRING); // H
            // I : vide.

            $ligne++;
        }

        $nbLignes = $ligne - 2;

        $nomFichier = $this->nomFichier($theme, $tranche, 'xlsx');
        $chemin = "paiements/{$nomFichier}";

        Storage::disk('local')->makeDirectory('paiements');
        $writer = new Xlsx($spreadsheet);
        $writer->save(Storage::disk('local')->path($chemin));
        $spreadsheet->disconnectWorksheets();

        return [
            'chemin' => $chemin,
            'nom_fichier' => $nomFichier,
            'nb_lignes' => $nbLignes,
            'montant_total' => $montantTotal,
        ];
    }
}
