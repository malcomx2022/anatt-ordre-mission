<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrdreMissionRequest;
use App\Http\Requests\UpdateOrdreMissionRequest;
use App\Http\Resources\OrdreMissionResource;
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

    public function index(Request $request): JsonResponse
    {
        $query = OrdreMission::with(['agent', 'taux']);

        if ($statut = $request->get('statut')) {
            $query->where('statut', $statut);
        }

        if ($agentId = $request->get('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($dateDebut = $request->get('date_debut')) {
            $query->where('date_depart', '>=', $dateDebut);
        }

        if ($dateFin = $request->get('date_fin')) {
            $query->where('date_retour', '<=', $dateFin);
        }

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $ordres = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return OrdreMissionResource::collection($ordres)->response();
    }

    public function store(StoreOrdreMissionRequest $request): JsonResponse
    {
        $ordre = $this->missionService->creer(
            $request->validated(),
            $request->user(),
        );

        return $this->created(
            new OrdreMissionResource($ordre->load(['agent', 'taux'])),
            'Ordre de mission cree avec succes.',
        );
    }

    public function show(OrdreMission $ordre): JsonResponse
    {
        $ordre->load(['agent', 'taux', 'valideurChef', 'valideurDG', 'paiements']);

        return $this->success(new OrdreMissionResource($ordre));
    }

    public function update(UpdateOrdreMissionRequest $request, OrdreMission $ordre): JsonResponse
    {
        $ordre = $this->missionService->modifier($ordre, $request->validated());

        return $this->success(new OrdreMissionResource($ordre), 'Ordre de mission mis a jour.');
    }

    public function destroy(OrdreMission $ordre): JsonResponse
    {
        $this->missionService->supprimer($ordre);

        return $this->success(null, 'Ordre de mission supprime.');
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

    public function historique(OrdreMission $ordre): JsonResponse
    {
        $logs = \App\Models\AuditLog::where('cible', "ordre_mission:{$ordre->id}")
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($logs);
    }
}
