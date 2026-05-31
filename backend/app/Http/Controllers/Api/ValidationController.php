<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrdreMissionResource;
use App\Models\OrdreMission;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /** Validation d'un OM retranscrit : IMPORTE → VALIDE. */
    public function valider(Request $request, OrdreMission $ordre): JsonResponse
    {
        $ordre = $this->missionService->valider($ordre, $request->user());

        return $this->success(
            new OrdreMissionResource($ordre->load(['agent', 'mission', 'versements'])),
            'Ordre de mission validé.',
        );
    }

    /** Rejet d'un OM avec motif obligatoire : IMPORTE → REJETE. */
    public function rejeter(Request $request, OrdreMission $ordre): JsonResponse
    {
        $request->validate([
            'motif' => 'required|string|min:10',
        ]);

        $ordre = $this->missionService->rejeter($ordre, $request->string('motif'), $request->user());

        return $this->success(
            new OrdreMissionResource($ordre->load(['agent', 'mission', 'versements'])),
            'Ordre de mission rejeté.',
        );
    }
}
