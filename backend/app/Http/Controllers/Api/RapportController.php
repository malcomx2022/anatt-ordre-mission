<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RapportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    public function __construct(
        private readonly RapportService $rapportService,
    ) {}

    public function mensuel(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->mensuel(
            $request->get('mois', now()->format('Y-m'))
        ));
    }

    public function agents(Request $request): JsonResponse
    {
        return $this->success($this->rapportService->parAgents(
            $request->integer('annee', now()->year)
        ));
    }

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'sometimes|in:missions,paiements,agents,complet',
            'format' => 'sometimes|in:xlsx,csv',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
        ]);

        return $this->success(
            $this->rapportService->exporter($request->all()),
            'Export genere avec succes.',
        );
    }

    public function exportDownload(Request $request)
    {
        $request->validate([
            'fichier' => 'required|string',
        ]);

        return $this->rapportService->telechargerExport($request->get('fichier'));
    }
}
