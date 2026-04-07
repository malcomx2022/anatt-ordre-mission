<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementResource;
use App\Models\OrdreMission;
use App\Models\Paiement;
use App\Services\MoovPaymentService;
use App\Services\MtnPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function __construct(
        private readonly MoovPaymentService $moovService,
        private readonly MtnPaymentService $mtnService,
    ) {}

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

        $resultat = $this->moovService->genererFichier($ordres);

        return $this->success([
            'fichier' => $resultat['fichier'],
            'nb_paiements' => $resultat['nb_paiements'],
            'montant_total' => $resultat['montant_total'],
            'paiements' => PaiementResource::collection($resultat['paiements']),
        ], "{$resultat['nb_paiements']} paiement(s) Moov genere(s).");
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

        $resultat = $this->mtnService->genererFichier($ordres);

        return $this->success([
            'fichier' => $resultat['fichier'],
            'nb_paiements' => $resultat['nb_paiements'],
            'montant_total' => $resultat['montant_total'],
            'paiements' => PaiementResource::collection($resultat['paiements']),
        ], "{$resultat['nb_paiements']} paiement(s) MTN genere(s).");
    }

    public function statuts(): JsonResponse
    {
        $statuts = Paiement::selectRaw('operateur, statut, count(*) as total, sum(montant) as montant_total')
            ->groupBy('operateur', 'statut')
            ->get();

        return $this->success($statuts);
    }

    public function reconcilier(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt',
            'operateur' => 'required|in:MOOV,MTN',
        ]);

        $contenu = file_get_contents($request->file('fichier')->getRealPath());

        $resultats = match ($request->operateur) {
            'MOOV' => $this->moovService->traiterRetour($contenu),
            'MTN' => $this->mtnService->traiterRetour($contenu),
        };

        return $this->success($resultats, "Reconciliation terminee : {$resultats['succes']} succes, {$resultats['echec']} echec(s).");
    }

    public function historique(Request $request): JsonResponse
    {
        $query = Paiement::with(['ordreMission', 'agent']);

        if ($periode = $request->get('periode')) {
            $date = Carbon::parse($periode);
            $query->whereMonth('created_at', $date->month)
                  ->whereYear('created_at', $date->year);
        }

        $paiements = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return PaiementResource::collection($paiements)->response();
    }
}
