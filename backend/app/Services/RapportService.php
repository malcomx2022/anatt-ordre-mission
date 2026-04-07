<?php

namespace App\Services;

use App\Models\OrdreMission;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        // Export Excel/PDF sera implemente avec Maatwebsite/Excel et DomPDF
        return [
            'url' => '/api/rapports/export/download',
            'format' => $params['format'] ?? 'excel',
        ];
    }
}
