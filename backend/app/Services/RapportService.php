<?php

namespace App\Services;

use App\Exports\AgentsExport;
use App\Exports\MissionsExport;
use App\Exports\PaiementsExport;
use App\Exports\RapportCompletExport;
use App\Models\OrdreMission;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class RapportService
{
    public function mensuel(string $mois): array
    {
        $date = Carbon::parse($mois);

        $missions = OrdreMission::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->get();

        $paiements = Paiement::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->get();

        return [
            'mois' => $mois,
            'total_missions' => $missions->count(),
            'missions_par_statut' => $missions->groupBy('statut')->map->count(),
            'total_paiements' => $paiements->count(),
            'montant_total' => $paiements->sum('montant'),
            'paiements_par_operateur' => $paiements->groupBy('operateur')->map->sum('montant'),
        ];
    }

    public function parAgents(int $annee): Collection
    {
        $missions = OrdreMission::with('agent')
            ->whereYear('created_at', $annee)
            ->get()
            ->groupBy('agent_id');

        return $missions->map(function ($agentMissions) {
            $agent = $agentMissions->first()->agent;

            return [
                'agent' => $agent,
                'total_missions' => $agentMissions->count(),
                'total_per_diem' => $agentMissions->sum('montant_per_diem'),
                'missions_par_statut' => $agentMissions->groupBy('statut')->map->count(),
            ];
        })->values();
    }

    public function exporter(array $params): array
    {
        $type = $params['type'] ?? 'complet';
        $format = $params['format'] ?? 'xlsx';
        $dateDebut = $params['date_debut'] ?? null;
        $dateFin = $params['date_fin'] ?? null;
        $extension = $format === 'csv' ? 'csv' : 'xlsx';

        $export = match ($type) {
            'missions' => new MissionsExport($dateDebut, $dateFin, $params['statut'] ?? null),
            'paiements' => new PaiementsExport($dateDebut, $dateFin, $params['operateur'] ?? null),
            'agents' => new AgentsExport($params['service'] ?? null),
            default => new RapportCompletExport($dateDebut, $dateFin),
        };

        $filename = "rapports/rapport_{$type}_" . now()->format('Ymd_His') . ".{$extension}";

        Excel::store($export, $filename, 'local');

        return [
            'url' => "/api/rapports/export/download?fichier={$filename}",
            'fichier' => $filename,
            'format' => $format,
            'type' => $type,
        ];
    }

    public function telechargerExport(string $fichier)
    {
        if (! Storage::disk('local')->exists($fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        $nom = basename($fichier);

        return Storage::disk('local')->download($fichier, $nom);
    }
}
