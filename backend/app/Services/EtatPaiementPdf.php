<?php

namespace App\Services;

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Support\MontantEnLettres;
use App\Support\PdfAssets;
use App\Support\Workflow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    /**
     * ÉTAT GÉNÉRAL de paiement : le document de clôture de la mission.
     *
     * Consolidation des DEUX tranches — avances ET reliquats — servie en flux
     * uniquement lorsque les deux états ont bouclé leur circuit (VALIDE).
     * Document synthétisé, sans entité propre ni troisième circuit : chaque
     * colonne provient de l'instantané FIGÉ de l'état de sa tranche, donc
     * l'état général reflète exactement ce que les signataires ont arrêté.
     */
    public function telechargerFinal(Mission $mission)
    {
        [$avance, $reliquat] = $this->etatsValides($mission);

        $pdf = Pdf::loadView('pdf.etat-final', $this->donneesFinal($avance, $reliquat))
            ->setPaper('A4', 'landscape')
            ->setOption(['defaultFont' => 'sans-serif']);

        return $pdf->download($this->nomFichierFinal($avance));
    }

    /**
     * Les deux états validés de la mission — 422 tant que la consolidation
     * n'est pas acquise (une tranche manquante ou encore en circuit).
     *
     * @return array{0:EtatPaiement,1:EtatPaiement}
     */
    private function etatsValides(Mission $mission): array
    {
        $etats = EtatPaiement::where('mission_id', $mission->id)->get()->keyBy('tranche');
        $avance = $etats[EtatPaiement::TRANCHE_AVANCE] ?? null;
        $reliquat = $etats[EtatPaiement::TRANCHE_RELIQUAT] ?? null;

        if (! $avance?->estValide() || ! $reliquat?->estValide()) {
            throw ValidationException::withMessages([
                'etat' => ["L'état général n'est établi qu'une fois l'état des avances ET l'état des reliquats validés."],
            ]);
        }

        return [$avance, $reliquat];
    }

    /**
     * Charge de l'état général. Exposée — comme `donnees()` — pour que les
     * garanties du document (totaux consolidés, montant en lettres) soient
     * vérifiables sans désassembler un PDF compressé.
     *
     * @return array<string,mixed>
     */
    public function donneesFinal(EtatPaiement $avance, EtatPaiement $reliquat): array
    {
        $mission = $avance->mission;
        $fmt = fn (int $n) => $n === 0 ? '-' : number_format($n, 0, ',', ' ');

        // Union des bénéficiaires des deux instantanés : la colonne AVANCE vient
        // de l'état des avances, la colonne RELIQUAT de l'état des reliquats —
        // chacune scellée par son circuit, jamais recalculée ici. L'identité de
        // la ligne vient du premier instantané qui la porte.
        $parNumero = [];
        foreach ([$avance, $reliquat] as $etat) {
            foreach ($etat->lignes ?? LignesEtatPaiement::pourMission($etat->mission_id) as $l) {
                $cle = (string) ($l['numero'] ?? $l['beneficiaire']);
                if (! isset($parNumero[$cle])) {
                    $parNumero[$cle] = $l;
                    $parNumero[$cle]['avance_brut'] = 0;
                    $parNumero[$cle]['reliquat_brut'] = 0;
                }
                if ($etat->estAvance()) {
                    $parNumero[$cle]['avance_brut'] = (int) $l['avance_brut'];
                } else {
                    $parNumero[$cle]['reliquat_brut'] = (int) $l['reliquat_brut'];
                }
            }
        }

        $lignes = array_map(function (array $l) use ($fmt) {
            // Le total de la ligne est la somme des DEUX tranches scellées, pas
            // le montant_total_brut d'un instantané pris isolément.
            $total = $l['avance_brut'] + $l['reliquat_brut'];
            $l['montant_total_brut'] = $total;
            $l['total'] = $fmt($total);
            $l['avance'] = $fmt($l['avance_brut']);
            $l['reliquat'] = $fmt($l['reliquat_brut']);

            return $l;
        }, array_values($parNumero));

        $totalAvance = (int) $avance->montant_total;
        $totalReliquat = (int) $reliquat->montant_total;
        $total = $totalAvance + $totalReliquat;

        return [
            'objet' => $mission?->objet,
            'destination' => $mission?->destination,
            'imputation' => $mission?->imputation_budgetaire,
            'periode' => $this->periode($mission?->date_depart, $mission?->date_retour),
            'lignes' => $lignes,
            'nb_beneficiaires' => count($lignes),
            'total' => $fmt($total),
            'total_avance' => $fmt($totalAvance),
            'total_reliquat' => $fmt($totalReliquat),
            // Mention d'arrêt sur le total GÉNÉRAL (avances + reliquats).
            'mention_arret' => MontantEnLettres::mention($total),
            // Les deux documents consolidés : registre, validation finale, signataire.
            'references' => array_map(fn (EtatPaiement $e) => [
                'libelle' => 'État '.($e->libelleTranche()),
                // Sans préfixe : le document pose lui-même le « N° ».
                'numero' => EtatPaiement::normaliserNumero($e->numero),
                'valide_le' => $e->valide_dg_le?->format('d/m/Y'),
                'signataire' => $e->valide_dg_nom,
                'fonction' => $e->valide_dg_fonction ?: 'Le Directeur Général',
            ], [$avance, $reliquat]),
            'date_generation' => now()->format('d/m/Y'),
        ];
    }

    private function nomFichierFinal(EtatPaiement $avance): string
    {
        $slug = $avance->numero !== null ? Str::slug($avance->numero) : '';

        // Même garde que `chemin()` : un numéro symbolique peut ne rien produire.
        $base = $slug !== '' ? $slug : "mission-{$avance->mission_id}";

        return "ETAT-GENERAL-{$base}.pdf";
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
        // Repli sur le calcul pour les états antérieurs à l'instantané (legacy) :
        // on le journalise, car un tel document est recalculé à la volée et n'a
        // pas la garantie d'intégrité de l'instantané figé.
        if ($etat->lignes === null) {
            Log::warning('État de paiement sans instantané « lignes » : tableau recalculé à la volée (legacy).', [
                'etat_id' => $etat->id,
                'mission_id' => $etat->mission_id,
                'tranche' => $etat->tranche,
                'statut' => $etat->statut,
            ]);
        }

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
            // Sans préfixe : le document pose lui-même le « N° ».
            'numero' => EtatPaiement::normaliserNumero($etat->numero),
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
