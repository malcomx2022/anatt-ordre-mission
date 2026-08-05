<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LotPaiementResource;
use App\Http\Resources\VersementResource;
use App\Jobs\GenererLotPaiementJob;
use App\Models\LotPaiement;
use App\Models\Versement;
use App\Services\LotPaiementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaiementController extends Controller
{
    public function __construct(
        private readonly LotPaiementService $lotService,
    ) {}

    /** Liste des lots de paiement (filtres : mission, tranche, opérateur, statut). */
    public function index(Request $request): JsonResponse
    {
        $query = LotPaiement::with('mission');

        foreach (['mission_id', 'tranche', 'operateur', 'statut'] as $champ) {
            if ($valeur = $request->get($champ)) {
                $query->where($champ, $valeur);
            }
        }

        $lots = $query->orderByDesc('created_at')->paginate($this->getPerPage($request));

        return LotPaiementResource::collection($lots)->response();
    }

    /** Prévisualise les bénéficiaires éligibles d'un lot. */
    public function previsualiser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mission_id' => 'required|integer|exists:missions,id',
            'tranche' => 'required|in:AVANCE,RELIQUAT',
            'operateur' => 'required|in:MTN,MOOV',
        ]);

        return $this->success(
            $this->lotService->previsualiser($data['mission_id'], $data['tranche'], $data['operateur']),
        );
    }

    /**
     * Génère (ou régénère) le fichier d'un lot.
     * Petit lot : téléchargement direct. Gros lot (> seuil) : bascule en file Redis (202 + tracking_id).
     */
    public function generer(Request $request)
    {
        $data = $request->validate([
            'mission_id' => 'required|integer|exists:missions,id',
            'tranche' => 'required|in:AVANCE,RELIQUAT',
            'operateur' => 'required|in:MTN,MOOV',
        ]);

        $apercu = $this->lotService->previsualiser($data['mission_id'], $data['tranche'], $data['operateur']);

        // Gros lot -> traitement asynchrone sur la file « paiements ».
        if ($apercu['nb_lignes'] > config('paiement.async_seuil')) {
            $trackingId = (string) Str::uuid();
            GenererLotPaiementJob::dispatch(
                $data['mission_id'], $data['tranche'], $data['operateur'], $request->user()->id, $trackingId,
            );

            return response()->json([
                'async' => true,
                'tracking_id' => $trackingId,
                'nb_lignes' => $apercu['nb_lignes'],
                'message' => 'Gros lot : génération lancée en file. Suivez la progression via tracking_id.',
            ], 202);
        }

        $resultat = $this->lotService->generer(
            $data['mission_id'], $data['tranche'], $data['operateur'], $request->user(),
        );

        return Storage::disk('local')->download($resultat['chemin'], basename($resultat['chemin']));
    }

    /** Suivi de progression d'une génération asynchrone de lot. */
    public function statutJob(string $trackingId): JsonResponse
    {
        return $this->success(Cache::get("lot_job:{$trackingId}", ['statut' => 'INTROUVABLE']));
    }

    /** Re-télécharge le fichier d'un lot déjà généré. */
    public function download(LotPaiement $lot)
    {
        abort_unless($lot->nom_fichier && Storage::disk('local')->exists($lot->nom_fichier), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($lot->nom_fichier, basename($lot->nom_fichier));
    }

    /** Confirmation manuelle d'un lot après dépôt réussi sur la plateforme opérateur. */
    public function confirmer(LotPaiement $lot, Request $request): JsonResponse
    {
        $lot = $this->lotService->confirmer($lot, $request->user());

        return $this->success(new LotPaiementResource($lot->load('mission')), 'Lot confirmé : versements soldés.');
    }

    /** Marque un lot en erreur (autorise la régénération). */
    public function marquerErreur(LotPaiement $lot, Request $request): JsonResponse
    {
        $lot = $this->lotService->marquerErreur($lot, $request->user());

        return $this->success(new LotPaiementResource($lot->load('mission')), 'Lot marqué en erreur.');
    }

    /** Synthèse des versements par opérateur et statut. */
    public function statuts(): JsonResponse
    {
        $statuts = Versement::selectRaw('operateur, statut, count(*) as total, sum(montant) as montant_total')
            ->groupBy('operateur', 'statut')
            ->get();

        return $this->success($statuts);
    }

    /** Historique des versements confirmés, filtrable par période (date_paiement). */
    public function historique(Request $request): JsonResponse
    {
        $query = Versement::with(['ordreMission.agent', 'ordreMission.mission'])
            ->where('statut', 'CONFIRME');

        if ($periode = $request->get('periode')) {
            $date = Carbon::parse($periode);
            $query->whereMonth('date_paiement', $date->month)
                  ->whereYear('date_paiement', $date->year);
        }

        if ($dateDebut = $request->get('date_debut')) {
            $query->where('date_paiement', '>=', $dateDebut);
        }
        if ($dateFin = $request->get('date_fin')) {
            // date_paiement est un TIMESTAMP : une date seule (« 2026-08-05 ») vaut
            // minuit et exclurait les paiements de la journée. On borne à la fin du jour.
            $query->where('date_paiement', '<=', Carbon::parse($dateFin)->endOfDay());
        }

        $versements = $query->orderByDesc('date_paiement')->paginate($this->getPerPage($request));

        // Resource (et non le modèle brut) : forme cohérente avec les autres listes,
        // sans exposer les colonnes internes ; porte le bénéficiaire et la mission.
        return VersementResource::collection($versements)->response();
    }
}
