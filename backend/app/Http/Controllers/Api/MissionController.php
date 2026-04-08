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

        // AGENT ne voit que ses propres OM
        $user = $request->user();
        if ($user->role === 'AGENT' && $user->agent) {
            $query->where('agent_id', $user->agent->id);
        }

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

        $ordres = $query->orderByDesc('created_at')->paginate($this->getPerPage($request));

        return OrdreMissionResource::collection($ordres)->response();
    }

    public function store(StoreOrdreMissionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Un AGENT ne peut creer un OM que pour lui-meme
        if ($user->role === 'AGENT' && $user->agent && (int) $data['agent_id'] !== $user->agent->id) {
            return $this->forbidden('Vous ne pouvez creer un ordre de mission que pour vous-meme.');
        }

        $ordre = $this->missionService->creer($data, $user);

        return $this->created(
            new OrdreMissionResource($ordre->load(['agent', 'taux'])),
            'Ordre de mission cree avec succes.',
        );
    }

    public function show(Request $request, OrdreMission $ordre): JsonResponse
    {
        $user = $request->user();

        // AGENT ne peut voir que ses propres OM
        if ($user->role === 'AGENT' && $user->agent && $ordre->agent_id !== $user->agent->id) {
            return $this->forbidden('Vous ne pouvez consulter que vos propres ordres de mission.');
        }

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
