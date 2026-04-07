<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrdreMission;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    public function mensuel(Request $request): JsonResponse
    {
        $mois = $request->get('mois', now()->format('Y-m'));
        $date = Carbon::parse($mois);

        $missions = OrdreMission::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->get();

        $paiements = Paiement::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->get();

        return response()->json([
            'data' => [
                'mois' => $mois,
                'total_missions' => $missions->count(),
                'missions_par_statut' => $missions->groupBy('statut')->map->count(),
                'total_paiements' => $paiements->count(),
                'montant_total' => $paiements->sum('montant'),
                'paiements_par_operateur' => $paiements->groupBy('operateur')->map->sum('montant'),
            ],
        ]);
    }

    public function agents(Request $request): JsonResponse
    {
        $annee = $request->get('annee', now()->year);

        $missions = OrdreMission::with('agent')
            ->whereYear('created_at', $annee)
            ->get()
            ->groupBy('agent_id');

        $rapport = $missions->map(function ($agentMissions) {
            $agent = $agentMissions->first()->agent;
            return [
                'agent' => $agent,
                'total_missions' => $agentMissions->count(),
                'total_per_diem' => $agentMissions->sum('montant_per_diem'),
                'missions_par_statut' => $agentMissions->groupBy('statut')->map->count(),
            ];
        })->values();

        return response()->json(['data' => $rapport]);
    }

    public function export(Request $request): JsonResponse
    {
        // Excel/PDF export will use Maatwebsite/Excel and DomPDF
        return response()->json([
            'message' => 'Export en cours de generation.',
            'data' => ['url' => '/api/rapports/export/download'],
        ]);
    }
}
