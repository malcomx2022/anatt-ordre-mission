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

    /**
     * Validation d'un OM en une étape : IMPORTE → VALIDE (permission om.valider).
     *
     * Voie de repli pour les missions sans état de paiement ; le circuit à trois
     * signatures se joue sur l'état (cf. EtatPaiementController).
     */
    public function valider(Request $request, OrdreMission $ordre): JsonResponse
    {
        $request->validate(['commentaire' => 'nullable|string|max:1000']);

        $ordre = $this->missionService->valider($ordre, $request->user(), $request->input('commentaire'));

        return $this->success(
            new OrdreMissionResource($ordre->load(['agent', 'mission', 'versements'])),
            'Ordre de mission validé.',
        );
    }

    /** Rejet motivé d'un OM depuis IMPORTE (avant validation). */
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
