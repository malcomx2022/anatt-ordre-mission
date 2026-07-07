<?php

namespace App\Services;

use App\Models\OrdreMission;
use Barryvdh\DomPDF\Facade\Pdf;
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

    public function genererOrdreMission(OrdreMission $ordre): string
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

        $data = [
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
            'date_generation' => now()->format('d/m/Y H:i'),
        ];

        // isRemoteEnabled volontairement absent : le logo est embarqué en data-URI,
        // aucune ressource distante n'est nécessaire. Le laisser actif exposerait un
        // SSRF / lecture de fichier local si une donnée utilisateur atterrissait un jour
        // dans un attribut src / url() du gabarit.
        $pdf = Pdf::loadView('pdf.ordre-mission', $data)
            ->setPaper('A4', 'portrait')
            ->setOption(['defaultFont' => 'sans-serif']);

        $filename = $this->chemin($ordre);
        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }

    public function telecharger(OrdreMission $ordre)
    {
        $filename = $this->chemin($ordre);

        if (! Storage::disk('local')->exists($filename)) {
            $filename = $this->genererOrdreMission($ordre);
        }

        return Storage::disk('local')->download($filename, basename($filename));
    }

    private function chemin(OrdreMission $ordre): string
    {
        return 'ordres/OM-'.Str::slug($ordre->reference_semo).'.pdf';
    }
}
