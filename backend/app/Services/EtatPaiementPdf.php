<?php

namespace App\Services;

use App\Models\EtatPaiement;
use App\Support\MontantEnLettres;
use App\Support\PdfAssets;
use App\Support\Workflow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ÉTAT DE PAIEMENT d'une mission, au format du document officiel ANaTT
 * (référence : N°0069/ANaTT/DAF/C-SFC).
 *
 * Un état = 1 mission × 1 TRANCHE, N bénéficiaires, 13 colonnes, sous-lignes
 * par type de prise en charge : seule la colonne DE LA TRANCHE est remplie
 * (l'autre porte « - »), mention d'arrêt en lettres sur le total de la tranche,
 * puis les deux blocs de signature DAF puis DG.
 *
 * Orientation paysage : 13 colonnes ne tiennent pas en portrait.
 */
class EtatPaiementPdf
{
    /** Mention du filigrane apposé sur les épreuves. */
    private const MENTION_BROUILLON = 'BROUILLON - NON VALIDE';

    public function generer(EtatPaiement $etat, bool $brouillon = false): string
    {
        $pdf = $this->construire($etat, $brouillon);

        $chemin = $this->chemin($etat, $brouillon);
        Storage::disk('local')->put($chemin, $pdf->output());

        return $chemin;
    }

    public function telecharger(EtatPaiement $etat, bool $brouillon = false)
    {
        // Le brouillon est une épreuve : il est servi en flux, jamais écrit dans
        // le dossier des états (pas de fichier provisoire laissé derrière).
        if ($brouillon) {
            return $this->construire($etat, true)
                ->download(basename($this->chemin($etat, true)));
        }

        // Toujours régénéré : l'état reflète les OM et les paramètres de
        // signature au moment où on le demande.
        $chemin = $this->generer($etat, false);

        return Storage::disk('local')->download($chemin, basename($chemin));
    }

    /** Construit le document DomPDF (gabarit + filigrane éventuel). */
    private function construire(EtatPaiement $etat, bool $brouillon): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadView('pdf.etat-paiement', $this->donnees($etat, $brouillon))
            ->setPaper('A4', 'landscape')
            ->setOption(['defaultFont' => 'sans-serif']);

        if ($brouillon) {
            $this->apposerFiligrane($pdf);
        }

        return $pdf;
    }

    /**
     * Charge transmise au gabarit. Exposée pour que les garanties du document
     * (montant en lettres, absence de signature sur un brouillon) soient
     * vérifiables sans désassembler un PDF compressé.
     *
     * @return array<string,mixed>
     */
    public function donnees(EtatPaiement $etat, bool $brouillon = false): array
    {
        $etat->load('mission');
        $mission = $etat->mission;

        $fmt = fn (int $n) => $n === 0 ? '-' : number_format($n, 0, ',', ' ');

        // Le tableau figé au récapitulatif fait foi : le document signé ne peut
        // pas s'imprimer différemment de ce qui a été présenté au signataire.
        // Repli sur le calcul pour les états antérieurs à l'instantané.
        $lignes = $etat->lignes ?? LignesEtatPaiement::pourMission($etat->mission_id);

        // L'état n'autorise que SA tranche : sa colonne est remplie, l'autre
        // porte « - ». La mention d'arrêt porte sur le total de la tranche.
        $estAvance = $etat->estAvance();
        $lignes = array_map(function (array $l) use ($estAvance) {
            $l['avance'] = $estAvance ? $l['avance'] : '-';
            $l['reliquat'] = $estAvance ? '-' : $l['reliquat'];

            return $l;
        }, $lignes);

        return [
            'brouillon' => $brouillon,
            'numero' => $etat->numero,
            'tranche' => $etat->tranche,
            'libelle_tranche' => $etat->libelleTranche(),
            'statut' => $etat->statut,
            // Le document dit lui-même qu'il a été rejeté, avec le motif.
            'rejet' => $etat->statut === 'REJETE' ? $etat->commentaire_rejet : null,
            'objet' => $mission?->objet,
            'destination' => $mission?->destination,
            'imputation' => $mission?->imputation_budgetaire,
            'periode' => $this->periode($mission?->date_depart, $mission?->date_retour),
            'lignes' => $lignes,
            'nb_beneficiaires' => count($lignes),
            'total' => $fmt((int) array_sum(array_column($lignes, 'montant_total_brut'))),
            'total_avance' => $estAvance ? $fmt($etat->montant_total) : '-',
            'total_reliquat' => $estAvance ? '-' : $fmt($etat->montant_total),
            // Mention d'arrêt sur le total de la tranche (avances OU reliquats).
            'mention_arret' => MontantEnLettres::mention($etat->montant_total),
            'date_generation' => now()->format('d/m/Y'),
            // Signatures : jamais rendues sur un brouillon, même si l'état en porte.
            'visa_daf' => $brouillon ? null : $this->signature($etat->visa_daf_nom, $etat->visa_daf_fonction, $etat->visa_daf_le, $etat->signature_daf_hash, $etat->signature_daf_image, 'daf'),
            'validation_dg' => $brouillon ? null : $this->signature($etat->valide_dg_nom, $etat->valide_dg_fonction, $etat->valide_dg_le, $etat->signature_dg_hash, $etat->signature_dg_image, 'dg'),
            'controle' => $brouillon ? null : $this->signature($etat->controle_nom, $etat->controle_fonction, $etat->controle_le, $etat->signature_controle_hash, $etat->signature_controle_image, 'controleur'),
        ];
    }

    /** @return array{nom:string, fonction:?string, date:string, code:?string, image:?string}|null */
    private function signature(?string $nom, ?string $fonction, mixed $date, ?string $hash, ?string $imageArchivee, string $poste): ?array
    {
        if (empty($nom) || $date === null) {
            return null;
        }

        return [
            'nom' => $nom,
            'fonction' => $fonction,
            'date' => $date->format('d/m/Y'),
            'code' => Workflow::codeVerification($hash),
            // Priorité au paraphe archivé au moment de la signature ; repli sur
            // le paraphe en poste pour les états antérieurs à l'archivage.
            'image' => PdfAssets::signatureDataUri($imageArchivee) ?? SignatureParametres::pour($poste)['image'],
        ];
    }

    private function periode(mixed $depart, mixed $retour): string
    {
        if ($depart === null || $retour === null) {
            return '—';
        }

        return 'Du '.$depart->locale('fr')->isoFormat('dddd D')
            .' au '.$retour->locale('fr')->isoFormat('dddd D MMMM YYYY');
    }

    /**
     * Filigrane diagonal, apposé via l'API canvas : DomPDF ignore tout élément
     * portant `transform: rotate()`, un filigrane CSS pivoté n'apparaîtrait pas.
     */
    private function apposerFiligrane(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $police = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');

        if ($police === null) {
            return;
        }

        // Paysage : 842 × 595 pt.
        foreach ([200, 400] as $y) {
            $dompdf->getCanvas()->page_text(80, $y, self::MENTION_BROUILLON, $police, 28, [0.93, 0.80, 0.82], 0.0, 0.0, -20.0);
        }
    }

    private function chemin(EtatPaiement $etat, bool $brouillon): string
    {
        $slug = $etat->numero !== null ? Str::slug($etat->numero) : '';

        // Un numéro symbolique peut ne rien produire après slug (« ### », « ... ») :
        // on retombe sur l'identifiant, garant absolu de l'unicité du fichier.
        $base = $slug !== '' ? $slug : "etat-{$etat->id}";
        $suffixe = $brouillon ? 'BROUILLON' : $etat->statut;

        return "etats/ETAT-{$base}-{$etat->tranche}-{$suffixe}.pdf";
    }
}
