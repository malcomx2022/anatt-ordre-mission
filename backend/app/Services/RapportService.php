<?php

namespace App\Services;

use App\Exports\EtatExport;
use App\Models\Versement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * États de paiement alimentés par les versements CONFIRMÉS.
 * Tous les montants et dates s'appuient sur date_paiement (TIMESTAMP complet).
 */
class RapportService
{
    /** État mensuel : synthèse + ventilation par statut (instantané du pipeline). */
    public function mensuel(string $mois): array
    {
        $date = Carbon::parse($mois.'-01');
        $debut = $date->copy()->startOfMonth();
        $fin = $date->copy()->endOfMonth();

        $base = $this->baseConfirmes(['date_debut' => $debut, 'date_fin' => $fin]);

        $synthese = (clone $base)
            ->selectRaw('COUNT(*) as nb_versements, COALESCE(SUM(versements.montant),0) as montant_total')
            ->first();

        return [
            'mois' => $mois,
            'periode' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],
            'synthese' => [
                'nb_versements' => (int) $synthese->nb_versements,
                'montant_total' => (int) $synthese->montant_total,
            ],
            'par_operateur' => $this->parOperateur(['date_debut' => $debut, 'date_fin' => $fin]),
            'par_tranche' => (clone $base)
                ->selectRaw('versements.type as tranche, COUNT(*) as nb, COALESCE(SUM(versements.montant),0) as montant')
                ->groupBy('versements.type')->get(),
            // Instantané global de tous les versements par statut (pipeline).
            'par_statut' => Versement::selectRaw('statut, COUNT(*) as nb, COALESCE(SUM(montant),0) as montant')
                ->groupBy('statut')->get(),
        ];
    }

    public function parAgent(array $filtres): Collection
    {
        return $this->baseConfirmes($filtres)
            ->selectRaw('agents.id as agent_id, agents.matricule, agents.nom, agents.prenoms,
                         COUNT(*) as nb_versements, COALESCE(SUM(versements.montant),0) as montant_total')
            ->groupBy('agents.id', 'agents.matricule', 'agents.nom', 'agents.prenoms')
            ->orderByDesc('montant_total')
            ->get();
    }

    public function parOperateur(array $filtres): Collection
    {
        return $this->baseConfirmes($filtres)
            ->selectRaw('versements.operateur, COUNT(*) as nb_versements, COALESCE(SUM(versements.montant),0) as montant_total')
            ->groupBy('versements.operateur')
            ->get();
    }

    public function parZone(array $filtres): Collection
    {
        return $this->baseConfirmes($filtres)
            ->selectRaw('ordre_missions.zone_code, COUNT(*) as nb_versements, COALESCE(SUM(versements.montant),0) as montant_total')
            ->groupBy('ordre_missions.zone_code')
            ->orderByDesc('montant_total')
            ->get();
    }

    public function parImputation(array $filtres): Collection
    {
        // Imputation absente normalisée à la SOURCE : l'endpoint JSON renvoie
        // « (non renseignée) » au lieu de null, cohérent avec l'export et l'UI
        // (plus besoin que chaque consommateur recolle l'étiquette).
        return $this->baseConfirmes($filtres)
            ->selectRaw("COALESCE(missions.imputation_budgetaire, '(non renseignée)') as imputation_budgetaire,
                         COUNT(*) as nb_versements, COALESCE(SUM(versements.montant),0) as montant_total")
            ->groupBy('missions.imputation_budgetaire')
            ->orderByDesc('montant_total')
            ->get();
    }

    /**
     * État des TROP-PERÇUS (régularisations) : reliquats négatifs après amendement.
     * Distinct des autres rapports (basés sur les versements CONFIRMÉS) : il porte
     * sur les statuts A_RECOUVRER / RECOUVRE. Les montants sont en valeur absolue
     * (un trop-perçu est stocké négatif). Cf. docs/amendement-om.md.
     */
    public function tropPercus(): array
    {
        $parStatut = Versement::query()
            ->whereIn('statut', ['A_RECOUVRER', 'RECOUVRE'])
            ->selectRaw('statut, COUNT(*) as nb, COALESCE(SUM(ABS(montant)),0) as montant')
            ->groupBy('statut')
            ->get()->keyBy('statut');

        $aRecouvrer = $parStatut->get('A_RECOUVRER');
        $recouvre = $parStatut->get('RECOUVRE');

        $bloc = fn ($r) => [
            'nb' => (int) ($r->nb ?? 0),
            'montant' => (int) ($r->montant ?? 0),
        ];

        return [
            'a_recouvrer' => $bloc($aRecouvrer),
            'recouvre' => $bloc($recouvre),
            'total' => [
                'nb' => (int) (($aRecouvrer->nb ?? 0) + ($recouvre->nb ?? 0)),
                'montant' => (int) (($aRecouvrer->montant ?? 0) + ($recouvre->montant ?? 0)),
            ],
            'par_operateur' => Versement::query()
                ->whereIn('statut', ['A_RECOUVRER', 'RECOUVRE'])
                ->selectRaw('operateur, statut, COUNT(*) as nb, COALESCE(SUM(ABS(montant)),0) as montant')
                ->groupBy('operateur', 'statut')
                ->orderBy('operateur')
                ->get(),
        ];
    }

    /** Génère un export (xlsx ou pdf) d'un état et le stocke ; renvoie ses métadonnées. */
    public function exporter(array $params): array
    {
        $dimension = $params['dimension'] ?? 'agent';
        $format = ($params['format'] ?? 'xlsx') === 'pdf' ? 'pdf' : 'xlsx';
        $filtres = array_intersect_key($params, array_flip(['date_debut', 'date_fin']));

        [$entetes, $lignes, $titre] = $this->tabulaire($dimension, $filtres);

        $fichier = "rapports/etat_{$dimension}_".now()->format('Ymd_His').".{$format}";

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.etat', [
                'titre' => $titre,
                'entetes' => $entetes,
                'lignes' => $lignes,
                'periode' => $filtres,
                'genere_le' => now()->format('d/m/Y H:i'),
            ])->setPaper('A4', 'landscape');
            Storage::disk('local')->put($fichier, $pdf->output());
        } else {
            Excel::store(new EtatExport($entetes, $lignes, $titre), $fichier, 'local');
        }

        return [
            'fichier' => $fichier,
            'url' => "/api/rapports/export/download?fichier={$fichier}",
            'dimension' => $dimension,
            'format' => $format,
            'nb_lignes' => count($lignes),
        ];
    }

    public function telechargerExport(string $fichier)
    {
        $fichier = ltrim($fichier, '/');
        if (str_contains($fichier, '..') || ! str_starts_with($fichier, 'rapports/')) {
            abort(403, 'Chemin de fichier non autorisé.');
        }
        if (! Storage::disk('local')->exists($fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->download($fichier, basename($fichier));
    }

    /** Requête de base : versements CONFIRMÉS joints aux OM, missions et agents. */
    private function baseConfirmes(array $filtres): Builder
    {
        $query = DB::table('versements')
            ->join('ordre_missions', 'versements.ordre_mission_id', '=', 'ordre_missions.id')
            ->join('missions', 'ordre_missions.mission_id', '=', 'missions.id')
            ->join('agents', 'ordre_missions.agent_id', '=', 'agents.id')
            ->where('versements.statut', 'CONFIRME');

        if ($debut = ($filtres['date_debut'] ?? null)) {
            $query->where('versements.date_paiement', '>=', $debut);
        }
        if ($fin = ($filtres['date_fin'] ?? null)) {
            // date_paiement est un TIMESTAMP : une date seule (« 2026-01-31 ») vaut
            // minuit et exclurait tous les paiements de cette journée. On borne à la
            // fin du jour pour couvrir la journée entière (endOfDay est idempotent
            // sur un Carbon déjà en fin de mois, cas de l'état mensuel).
            $query->where('versements.date_paiement', '<=', Carbon::parse($fin)->endOfDay());
        }

        return $query;
    }

    /** Prépare en-têtes + lignes tabulaires pour l'export d'une dimension. */
    private function tabulaire(string $dimension, array $filtres): array
    {
        return match ($dimension) {
            'operateur' => [
                ['Opérateur', 'Nb versements', 'Montant total (FCFA)'],
                $this->parOperateur($filtres)->map(fn ($r) => [$r->operateur, $r->nb_versements, $r->montant_total])->all(),
                'État par opérateur',
            ],
            'zone' => [
                ['Zone', 'Nb versements', 'Montant total (FCFA)'],
                $this->parZone($filtres)->map(fn ($r) => [$r->zone_code, $r->nb_versements, $r->montant_total])->all(),
                'État par zone',
            ],
            'imputation' => [
                ['Imputation budgétaire', 'Nb versements', 'Montant total (FCFA)'],
                $this->parImputation($filtres)->map(fn ($r) => [$r->imputation_budgetaire, $r->nb_versements, $r->montant_total])->all(),
                'État par imputation budgétaire',
            ],
            default => [
                ['Matricule', 'Bénéficiaire', 'Nb versements', 'Montant total (FCFA)'],
                $this->parAgent($filtres)->map(fn ($r) => [$r->matricule, trim("{$r->nom} {$r->prenoms}"), $r->nb_versements, $r->montant_total])->all(),
                'État par agent',
            ],
        };
    }
}
