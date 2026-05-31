<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenererEtatJob;
use App\Services\RapportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RapportController extends Controller
{
    public function __construct(
        private readonly RapportService $rapportService,
    ) {}

    public function mensuel(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->mensuel(
            $request->get('mois', now()->format('Y-m')),
        ));
    }

    public function agents(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->parAgent($this->filtres($request)));
    }

    public function operateurs(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->parOperateur($this->filtres($request)));
    }

    public function zones(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->parZone($this->filtres($request)));
    }

    public function imputations(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->parImputation($this->filtres($request)));
    }

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'dimension' => 'sometimes|in:agent,operateur,zone,imputation',
            'format' => 'sometimes|in:xlsx,pdf',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
        ]);

        return $this->success(
            $this->rapportService->exporter($request->all()),
            'Export généré avec succès.',
        );
    }

    public function exportDownload(Request $request)
    {
        $request->validate(['fichier' => 'required|string']);

        return $this->rapportService->telechargerExport($request->get('fichier'));
    }

    /** Production asynchrone d'un état (file Redis « etats »). */
    public function exportAsync(Request $request): JsonResponse
    {
        $params = $request->validate([
            'dimension' => 'sometimes|in:agent,operateur,zone,imputation',
            'format' => 'sometimes|in:xlsx,pdf',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
        ]);

        $trackingId = (string) Str::uuid();
        GenererEtatJob::dispatch($params, $trackingId);

        return response()->json([
            'async' => true,
            'tracking_id' => $trackingId,
            'message' => 'État en cours de production en file. Suivez la progression via tracking_id.',
        ], 202);
    }

    /** Suivi de progression d'une production d'état asynchrone. */
    public function statutExport(string $trackingId): JsonResponse
    {
        return $this->success(Cache::get("etat_job:{$trackingId}", ['statut' => 'INTROUVABLE']));
    }

    /** @return array<string,mixed> */
    private function filtres(Request $request): array
    {
        return $request->only(['date_debut', 'date_fin']);
    }
}
