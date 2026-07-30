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
 * Un état = 1 mission, N bénéficiaires, 13 colonnes, sous-lignes par type de
 * prise en charge, colonnes AVANCE et RELIQUAT, mention d'arrêt en lettres sur
 * le TOTAL général, puis les deux blocs de signature DAF puis DG.
 *
 * Orientation paysage : 13 colonnes ne tiennent pas en portrait.
 */
class EtatPaiementPdf
{
    /** Mention du filigrane apposé sur les épreuves. */
    private const MENTION_BROUILLON = 'BROUILLON - NON VALIDE';

    /** Rend le document et renvoie ses octets, sans toucher au disque. */
    public function rendre(EtatPaiement $etat, bool $brouillon = false): string
    {
        $pdf = Pdf::loadView('pdf.etat-paiement', $this->donnees($etat, $brouillon))
            ->setPaper('A4', 'landscape')
            ->setOption(['defaultFont' => 'sans-serif']);

        if ($brouillon) {
            $this->apposerFiligrane($pdf);
        }

        return (string) $pdf->output();
    }

    public function generer(EtatPaiement $etat, bool $brouillon = false): string
    {
        $chemin = $this->chemin($etat, $brouillon);
        Storage::disk('local')->put($chemin, $this->rendre($etat, $brouillon));

        return $chemin;
    }

    public function telecharger(EtatPaiement $etat, bool $brouillon = false)
    {
        // Le document part en flux, sans passer par un fichier partagé : deux
        // agents qui téléchargent le même état au même instant ne peuvent plus
        // se marcher dessus, ni recevoir l'état d'une autre mission.
        $contenu = $this->rendre($etat, $brouillon);
        $nom = basename($this->chemin($etat, $brouillon));

        return response()->streamDownload(
            function () use ($contenu) {
                echo $contenu;
            },
            $nom,
            ['Content-Type' => 'application/pdf'],
        );
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

        $fmt = fn (int $n) => LignesEtatPaiement::fmt($n);

        // L'instantané figé au récapitulatif fait foi : c'est le tableau qui a
        // été présenté au signataire. On ne recalcule que pour les états
        // antérieurs à l'instantané, qui n'en portent pas.
        $lignes = $etat->lignes ?: LignesEtatPaiement::pourMission($etat->mission_id);

        return [
            'brouillon' => $brouillon,
            'numero' => $etat->numero,
            'statut' => $etat->statut,
            // Un état retoqué ne doit pas circuler sous les traits d'un simple
            // brouillon : le document dit lui-même qu'il a été rejeté, et pourquoi.
            'rejet' => $etat->statut === 'REJETE' ? ($etat->commentaire_rejet ?: 'Motif non précisé') : null,
            'objet' => $mission?->objet,
            'destination' => $mission?->destination,
            'imputation' => $mission?->imputation_budgetaire,
            'periode' => $this->periode($mission?->date_depart, $mission?->date_retour),
            'lignes' => $lignes,
            'nb_beneficiaires' => count($lignes),
            'total' => $fmt($etat->montant_total),
            'total_avance' => $fmt($etat->montant_avance),
            'total_reliquat' => $fmt($etat->montant_reliquat),
            // Mention d'arrêt sur le TOTAL GÉNÉRAL (avance + reliquat).
            'mention_arret' => MontantEnLettres::mention($etat->montant_total),
            'date_generation' => now()->format('d/m/Y'),
            // Signatures : jamais rendues sur un brouillon, même si l'état en porte.
            'visa_daf' => $brouillon ? null : $this->signature($etat->visa_daf_nom, $etat->visa_daf_fonction, $etat->visa_daf_le, $etat->signature_daf_hash, $etat->signature_daf_image, 'daf'),
            'validation_dg' => $brouillon ? null : $this->signature($etat->valide_dg_nom, $etat->valide_dg_fonction, $etat->valide_dg_le, $etat->signature_dg_hash, $etat->signature_dg_image, 'dg'),
            'controle' => $brouillon ? null : $this->signature($etat->controle_nom, $etat->controle_fonction, $etat->controle_le, $etat->signature_controle_hash, $etat->signature_controle_image, 'controleur'),
        ];
    }

    /**
     * Bloc de signature imprimé.
     *
     * Le paraphe vient de la copie ARCHIVÉE au moment de la signature, jamais du
     * paraphe courant du poste : un état signé porte le paraphe de celui dont il
     * porte le nom, même si le titulaire a changé depuis. Le repli sur les
     * paramètres ne sert qu'aux états signés avant l'archivage.
     *
     * @return array{nom:string, fonction:?string, date:string, code:?string, image:?string}|null
     */
    private function signature(?string $nom, ?string $fonction, mixed $date, ?string $hash, ?string $archive, string $poste): ?array
    {
        if (empty($nom) || $date === null) {
            return null;
        }

        return [
            'nom' => $nom,
            'fonction' => $fonction,
            'date' => $date->format('d/m/Y'),
            'code' => Workflow::codeVerification($hash),
            'image' => PdfAssets::signatureDataUri($archive) ?? SignatureParametres::pour($poste)['image'],
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

    /**
     * Chemin du document, TOUJOURS préfixé par l'identifiant de l'état.
     *
     * Le numéro seul ne suffit pas à distinguer deux états : il est saisi à la
     * main, et un numéro purement symbolique (« ### », « — ») se réduit à une
     * chaîne vide une fois slugifié. Deux états se retrouvaient alors à écrire
     * dans le même fichier — au mieux un écrasement, au pire l'état d'une autre
     * mission servi en téléchargement.
     */
    private function chemin(EtatPaiement $etat, bool $brouillon): string
    {
        $slug = $etat->numero !== null ? Str::slug($etat->numero) : '';
        $base = $slug !== '' ? "{$etat->id}-{$slug}" : "{$etat->id}-mission-{$etat->mission_id}";
        $suffixe = $brouillon ? 'BROUILLON' : $etat->statut;

        return "etats/ETAT-{$base}-{$suffixe}.pdf";
    }
}
