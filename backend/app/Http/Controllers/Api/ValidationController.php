<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrdreMissionResource;
use App\Models\OrdreMission;
use App\Services\MissionService;
use App\Support\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * Étape 1 — contrôle de conformité par le CONTROLEUR.
     *
     * Circuit complet : IMPORTE → VERIFIE (le DAF prend ensuite la main).
     * Circuit historique : IMPORTE → VALIDE (comportement d'origine conservé).
     */
    public function valider(Request $request, OrdreMission $ordre): JsonResponse
    {
        $request->validate(['commentaire' => 'nullable|string|max:1000']);

        $ordre = $this->missionService->valider($ordre, $request->user(), $request->input('commentaire'));

        return $this->success(
            new OrdreMissionResource($ordre->load(['agent', 'mission', 'versements'])),
            $ordre->statut === Workflow::STATUT_VERIFIE
                ? 'Ordre de mission vérifié : transmis au DAF pour visa.'
                : 'Ordre de mission validé.',
        );
    }

    /** Étape 2 — visa du DAF : VERIFIE → VISA_DAF. L'OM n'est pas encore payable. */
    public function viser(Request $request, OrdreMission $ordre): JsonResponse
    {
        $request->validate(['commentaire' => 'nullable|string|max:1000']);

        $ordre = $this->missionService->viser($ordre, $request->user(), $request->input('commentaire'));

        return $this->success(
            new OrdreMissionResource($ordre->load(['agent', 'mission', 'versements'])),
            'Visa du DAF apposé : transmis au Directeur Général pour validation finale.',
        );
    }

    /**
     * Étape 3 — validation finale du Directeur Général : VISA_DAF → VALIDE.
     * C'est cette étape qui verrouille l'OM et le rend payable.
     */
    public function approuver(Request $request, OrdreMission $ordre): JsonResponse
    {
        $ordre = $this->missionService->approuver($ordre, $request->user());

        return $this->success(
            new OrdreMissionResource($ordre->load(['agent', 'mission', 'versements'])),
            'Ordre de mission validé et verrouillé par le Directeur Général.',
        );
    }

    /** Rejet motivé, possible à chacune des trois étapes du circuit. */
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
