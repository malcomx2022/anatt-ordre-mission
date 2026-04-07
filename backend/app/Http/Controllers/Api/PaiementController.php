<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementResource;
use App\Models\OrdreMission;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Paiement::with(['ordreMission', 'agent']);

        if ($operateur = $request->get('operateur')) {
            $query->where('operateur', $operateur);
        }

        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        $paiements = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return PaiementResource::collection($paiements)->response();
    }

    public function genererMoov(Request $request): JsonResponse
    {
        $request->validate([
            'ordre_ids' => 'required|array',
            'ordre_ids.*' => 'exists:ordre_missions,id',
        ]);

        $ordres = OrdreMission::with('agent')
            ->whereIn('id', $request->ordre_ids)
            ->where('statut', 'APPROUVE_DG')
            ->get();

        $paiements = [];
        foreach ($ordres as $ordre) {
            if (! $ordre->agent->numero_moov) {
                continue;
            }

            $paiements[] = Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $ordre->agent_id,
                'operateur' => 'MOOV',
                'numero_destinataire' => $ordre->agent->numero_moov,
                'montant' => $ordre->montant_per_diem,
                'statut' => 'EN_ATTENTE',
            ]);
        }

        return response()->json([
            'data' => PaiementResource::collection($paiements),
            'message' => count($paiements) . ' paiement(s) Moov genere(s).',
        ]);
    }

    public function genererMtn(Request $request): JsonResponse
    {
        $request->validate([
            'ordre_ids' => 'required|array',
            'ordre_ids.*' => 'exists:ordre_missions,id',
        ]);

        $ordres = OrdreMission::with('agent')
            ->whereIn('id', $request->ordre_ids)
            ->where('statut', 'APPROUVE_DG')
            ->get();

        $paiements = [];
        foreach ($ordres as $ordre) {
            if (! $ordre->agent->numero_mtn) {
                continue;
            }

            $paiements[] = Paiement::create([
                'ordre_mission_id' => $ordre->id,
                'agent_id' => $ordre->agent_id,
                'operateur' => 'MTN',
                'numero_destinataire' => $ordre->agent->numero_mtn,
                'montant' => $ordre->montant_per_diem,
                'statut' => 'EN_ATTENTE',
            ]);
        }

        return response()->json([
            'data' => PaiementResource::collection($paiements),
            'message' => count($paiements) . ' paiement(s) MTN genere(s).',
        ]);
    }

    public function statuts(): JsonResponse
    {
        $statuts = Paiement::selectRaw('operateur, statut, count(*) as total, sum(montant) as montant_total')
            ->groupBy('operateur', 'statut')
            ->get();

        return response()->json(['data' => $statuts]);
    }

    public function reconcilier(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt',
        ]);

        // Reconciliation logic to be implemented
        return response()->json([
            'message' => 'Reconciliation en cours de traitement.',
        ]);
    }

    public function historique(Request $request): JsonResponse
    {
        $query = Paiement::with(['ordreMission', 'agent']);

        if ($periode = $request->get('periode')) {
            $query->whereMonth('created_at', Carbon::parse($periode)->month)
                  ->whereYear('created_at', Carbon::parse($periode)->year);
        }

        $paiements = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return PaiementResource::collection($paiements)->response();
    }
}
