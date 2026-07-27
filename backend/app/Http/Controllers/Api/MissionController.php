<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMissionRequest;
use App\Http\Resources\MissionResource;
use App\Http\Resources\OrdreMissionResource;
use App\Models\AuditLog;
use App\Models\OrdreMission;
use App\Services\MissionService;
use App\Services\PdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
        private readonly PdfGenerator $pdfGenerator,
    ) {}

    /**
     * Liste filtrée des ordres de mission.
     * Filtres : statut, période (date_debut/date_fin), opérateur, zone, agent, recherche.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrdreMission::with(['agent', 'mission', 'versements']);

        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        if ($zone = $request->get('zone')) {
            $query->where('zone_code', $zone);
        }

        if ($agentId = $request->get('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($operateur = $request->get('operateur')) {
            $query->whereHas('agent', fn ($q) => $q->where('operateur', $operateur));
        }

        if ($dateDebut = $request->get('date_debut')) {
            $query->whereHas('mission', fn ($q) => $q->where('date_depart', '>=', $dateDebut));
        }

        if ($dateFin = $request->get('date_fin')) {
            $query->whereHas('mission', fn ($q) => $q->where('date_retour', '<=', $dateFin));
        }

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $ordres = $query->orderByDesc('created_at')->paginate($this->getPerPage($request));

        return OrdreMissionResource::collection($ordres)->response();
    }

    public function show(OrdreMission $ordre): JsonResponse
    {
        $ordre->load(['agent', 'mission', 'versements']);

        return $this->success(new OrdreMissionResource($ordre));
    }

    /**
     * Retranscription d'une mission SEMO : 1 mission + N ordres de mission
     * (participants + CAV), avec calcul d'indemnité et versements EN_ATTENTE.
     */
    public function storeMission(StoreMissionRequest $request): JsonResponse
    {
        $mission = $this->missionService->creerAvecOrdres($request->validated(), $request->user());

        return $this->created(
            new MissionResource($mission),
            'Mission retranscrite : '.$mission->ordresMission->count().' ordre(s) de mission créé(s).',
        );
    }

    public function pdf(OrdreMission $ordre): JsonResponse
    {
        $filename = $this->pdfGenerator->genererOrdreMission($ordre);

        return $this->success([
            'url' => "/api/ordres/{$ordre->id}/pdf/download",
            'fichier' => $filename,
        ], 'PDF genere.');
    }

    public function pdfDownload(OrdreMission $ordre)
    {
        return $this->pdfGenerator->telecharger($ordre);
    }

    /**
     * Brouillon de l'OM : épreuve filigranée téléchargeable AVANT validation.
     *
     * Route distincte de /pdf plutôt qu'un paramètre : la sémantique du PDF
     * officiel reste intacte et les deux usages se distinguent dans l'audit.
     * Toujours régénéré (jamais servi depuis le cache) : un brouillon doit
     * refléter l'état courant de l'OM au moment où on le demande.
     */
    public function pdfBrouillon(OrdreMission $ordre)
    {
        $this->pdfGenerator->genererOrdreMission($ordre, brouillon: true);

        return $this->pdfGenerator->telecharger($ordre, brouillon: true);
    }

    public function historique(OrdreMission $ordre): JsonResponse
    {
        $logs = AuditLog::where('entite', 'ordre_mission')
            ->where('entite_id', $ordre->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($logs);
    }
}
