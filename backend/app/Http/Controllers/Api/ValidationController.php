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

    public function soumettre(OrdreMission $ordre): JsonResponse
    {
        $ordre = $this->missionService->soumettre($ordre);

        return $this->success(new OrdreMissionResource($ordre), 'Ordre de mission soumis pour validation.');
    }

    public function validerN1(Request $request, OrdreMission $ordre): JsonResponse
    {
        $ordre = $this->missionService->validerN1($ordre, $request->user());

        return $this->success(new OrdreMissionResource($ordre), 'Ordre de mission valide par le chef de service.');
    }

    public function approuver(Request $request, OrdreMission $ordre): JsonResponse
    {
        $ordre = $this->missionService->approuver($ordre, $request->user());

        return $this->success(new OrdreMissionResource($ordre), 'Ordre de mission approuve par la direction.');
    }

    public function rejeter(Request $request, OrdreMission $ordre): JsonResponse
    {
        $request->validate([
            'commentaire' => 'required|string|min:10',
        ]);

        $ordre = $this->missionService->rejeter($ordre, $request->commentaire);

        return $this->success(new OrdreMissionResource($ordre), 'Ordre de mission rejete.');
    }
}
