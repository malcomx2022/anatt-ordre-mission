<?php

namespace App\Services;

use App\Models\OrdreMission;
use App\Models\User;
use App\Support\Parametres;
use App\Support\PdfAssets;
use App\Support\Workflow;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfGenerator
{
    private const ZONES = [
        'INTERIEUR' => 'Intérieur (Bénin)',
        'EXT_ZONE_I' => 'Extérieur — Zone I',
        'EXT_ZONE_II' => 'Extérieur — Zone II',
        'EXT_ZONE_III' => 'Extérieur — Zone III',
    ];

    private const TYPES = [
        'UN_REPAS' => 'Un repas',
        'DEUX_REPAS' => 'Deux repas',
        'JOURNEE_COMPLETE' => 'Journée complète',
        'LOGE_NOURRI' => 'Logé + nourri',
        'LOGE_SEUL' => 'Logé seul',
        'NOURRI_SEUL' => 'Nourri seul',
    ];

    /** Libellés officiels des intervenants du circuit (blocs de signature). */
    private const LIBELLE_CONTROLE = 'Le Contrôleur — vérification';

    private const LIBELLE_DAF = "Le Directeur de l'Administration et des Finances";

    private const LIBELLE_DG = 'Le Directeur Général';

    /** Mention du filigrane apposé sur les épreuves. */
    private const MENTION_BROUILLON = 'BROUILLON - NON VALIDE';

    /**
     * Génère le PDF de l'ordre de mission.
     *
     * @param  bool  $brouillon  true => épreuve filigranée, sans valeur d'autorisation
     */
    public function genererOrdreMission(OrdreMission $ordre, bool $brouillon = false): string
    {
        $data = $this->donnees($ordre, $brouillon);

        // isRemoteEnabled volontairement absent : le logo et les signatures sont
        // embarqués en data-URI, aucune ressource distante n'est nécessaire. Le laisser
        // actif exposerait un SSRF / lecture de fichier local si une donnée utilisateur
        // atterrissait un jour dans un attribut src / url() du gabarit.
        $pdf = Pdf::loadView('pdf.ordre-mission', $data)
            ->setPaper('A4', 'portrait')
            ->setOption(['defaultFont' => 'sans-serif']);

        if ($brouillon) {
            $this->apposerFiligrane($pdf);
        }

        $filename = $this->chemin($ordre, $brouillon);
        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Charge transmise au gabarit.
     *
     * Exposée pour que la garantie « aucune signature sur un brouillon » soit
     * vérifiable directement, sans avoir à désassembler un PDF compressé.
     *
     * @return array<string,mixed>
     */
    public function donnees(OrdreMission $ordre, bool $brouillon = false): array
    {
        $ordre->load(['agent', 'mission']);
        $agent = $ordre->agent;
        $mission = $ordre->mission;

        $tauxJournalier = $ordre->nb_jours > 0 ? intdiv($ordre->montant_total, $ordre->nb_jours) : 0;
        $fmt = fn (int $n) => number_format($n, 0, ',', ' ');

        // Détail des lignes de per diem (état ANaTT) : type × taux × nb_jours = montant.
        $lignes = array_map(fn (array $l) => [
            'type' => self::TYPES[$l['type']] ?? $l['type'],
            'taux' => $fmt((int) $l['taux']),
            'nb_jours' => (int) $l['nb_jours'],
            'montant' => $fmt((int) $l['montant']),
        ], $ordre->lignes_per_diem ?? []);
        $montantTransport = (int) $ordre->montant_transport;
        $perDiem = $ordre->montant_total - $montantTransport;

        return [
            'reference_semo' => $ordre->reference_semo,
            'role' => $ordre->role,
            'statut' => $ordre->statut,
            'beneficiaire' => trim("{$agent->nom} {$agent->prenoms}"),
            'matricule' => $agent->matricule,
            'categorie' => $ordre->categorie,
            'telephone' => $agent->telephone,
            'operateur' => $agent->operateur,
            'grade' => $agent->grade,
            'objet' => $mission?->objet,
            'destination' => $mission?->destination,
            'zone' => self::ZONES[$ordre->zone_code] ?? $ordre->zone_code,
            'type_prise_en_charge' => self::TYPES[$ordre->type_prise_en_charge] ?? $ordre->type_prise_en_charge,
            'date_depart' => $mission?->date_depart?->format('d/m/Y'),
            'date_retour' => $mission?->date_retour?->format('d/m/Y'),
            'nb_jours' => $ordre->nb_jours,
            'imputation' => $mission?->imputation_budgetaire,
            'moyen_transport' => $ordre->moyen_transport,
            'conducteur_a_bord' => $ordre->conducteur_a_bord,
            'reference_autorisation' => $ordre->reference_autorisation,
            'exonere' => (bool) $mission?->prise_en_charge_tiers,
            'taux_journalier' => $fmt($tauxJournalier),
            'lignes' => $lignes,
            'montant_transport' => $fmt($montantTransport),
            'a_transport' => $montantTransport > 0,
            'per_diem' => $fmt($perDiem),
            'montant_total' => $fmt($ordre->montant_total),
            'avance' => $fmt($ordre->avance),
            'reliquat' => $fmt($ordre->reliquat),
            // Pourcentages paramétrables (le reliquat absorbe l'arrondi) — libellés dynamiques.
            'avance_pct' => Parametres::partAvance(),
            'reliquat_pct' => 100 - Parametres::partAvance(),
            'date_generation' => now()->format('d/m/Y H:i'),
            // ── Circuit de validation ──
            // Sur un brouillon, les signatures ne sont JAMAIS rendues, même si
            // l'OM en porte déjà : une épreuve ne doit pas exhiber de visa.
            'brouillon' => $brouillon,
            'libelle_controle' => self::LIBELLE_CONTROLE,
            'libelle_daf' => self::LIBELLE_DAF,
            'libelle_dg' => self::LIBELLE_DG,
            'verification' => $brouillon ? null : $this->signature(
                $ordre->verifie_nom,
                $ordre->verifie_le,
                $ordre->signature_verif_hash,
                $ordre->verifiePar,
                $ordre->verifie_fonction,
            ),
            'visa_daf' => $brouillon ? null : $this->signature(
                $ordre->visa_daf_nom,
                $ordre->visa_daf_le,
                $ordre->signature_daf_hash,
                $ordre->visaDaf,
                $ordre->visa_daf_fonction,
            ),
            'valide_dg' => $brouillon ? null : $this->signature(
                $ordre->valide_dg_nom,
                $ordre->valide_dg_le,
                $ordre->signature_dg_hash,
                $ordre->valideDg,
                $ordre->valide_dg_fonction,
            ),
        ];
    }

    public function telecharger(OrdreMission $ordre, bool $brouillon = false)
    {
        $filename = $this->chemin($ordre, $brouillon);

        if (! Storage::disk('local')->exists($filename)) {
            $filename = $this->genererOrdreMission($ordre, $brouillon);
        }

        return Storage::disk('local')->download($filename, basename($filename));
    }

    /**
     * Chemin de cache du PDF.
     *
     * Il dépend de l'ÉTAT du document : un brouillon et un document signé ne
     * partagent jamais le même fichier, sinon l'un écraserait l'autre — ou pire,
     * un document signé serait servi en réponse à une demande de brouillon.
     * Le suffixe suit le statut, de sorte qu'une signature invalide d'office le
     * cache de l'étape précédente.
     */
    private function chemin(OrdreMission $ordre, bool $brouillon = false): string
    {
        $slug = Str::slug($ordre->reference_semo);

        if ($brouillon) {
            return "ordres/OM-{$slug}-BROUILLON.pdf";
        }

        $etat = match ($ordre->statut) {
            'VISA_DAF' => 'VISA-DAF',
            'VALIDE', 'SOLDE' => 'FINAL',
            default => $ordre->statut,
        };

        return "ordres/OM-{$slug}-{$etat}.pdf";
    }

    /**
     * Appose le filigrane « BROUILLON » en diagonale sur CHAQUE page.
     *
     * Passe par l'API canvas de DomPDF et non par du CSS : DomPDF ignore
     * silencieusement tout élément portant `transform: rotate()` (vérifié — un
     * filigrane CSS pivoté n'apparaît tout simplement pas dans le PDF produit).
     * page_text() s'applique nativement à toutes les pages et gère l'angle.
     */
    private function apposerFiligrane(\Barryvdh\DomPDF\PDF $pdf): void
    {
        // render() doit précéder l'accès au canvas ; le wrapper mémorise l'état
        // rendu, output() ne relancera donc pas le rendu.
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $police = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');

        if ($police === null) {
            return; // police indisponible : on renonce au filigrane plutôt que d'échouer
        }

        // Rouge très clair : page_text n'a pas de canal alpha, la transparence est
        // simulée par la teinte pour rester lisible sans masquer le contenu.
        $teinte = [0.93, 0.80, 0.82];

        // Trois passes en diagonale pour couvrir la hauteur de la page A4 (842 pt).
        foreach ([250, 500, 750] as $y) {
            $canvas->page_text(60, $y, self::MENTION_BROUILLON, $police, 26, $teinte, 0.0, 0.0, -28.0);
        }
    }

    /**
     * Bloc de signature prêt à l'affichage, ou null si l'étape n'est pas franchie.
     *
     * Le nom affiché est l'INSTANTANÉ pris au moment de la signature : un
     * renommage, une mutation ou la suppression du compte signataire ne doit pas
     * réécrire un document déjà signé. Le compte n'est consulté que pour l'image.
     *
     * @return array{nom:string, fonction:?string, date:string, code:?string, image:?string}|null
     */
    private function signature(?string $nom, ?Carbon $date, ?string $hash, ?User $signataire, ?string $fonction = null): ?array
    {
        if (empty($nom) || $date === null) {
            return null;
        }

        return [
            'nom' => $nom,
            // Fonction figée à la signature : le document imprime le titre réel du
            // signataire, pas un libellé générique. Le gabarit retombe sur la
            // constante de rôle quand elle est absente (ou avant signature).
            'fonction' => $fonction ?: null,
            'date' => $date->format('d/m/Y à H:i'),
            'code' => Workflow::codeVerification($hash),
            'image' => PdfAssets::signatureDataUri($signataire?->signature_path),
        ];
    }
}
