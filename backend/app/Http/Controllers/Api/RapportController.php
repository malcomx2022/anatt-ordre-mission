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
        $mois = $request->get('mois', now()->format('Y-m'));

        return response()->json([
            'data' => $this->rapportService->mensuel($mois),
        ]);
    }

    public function agents(Request $request): JsonResponse
    {
        $annee = $request->integer('annee', now()->year);

        return response()->json([
            'data' => $this->rapportService->parAgents($annee),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $resultat = $this->rapportService->exporter($request->all());

        return response()->json([
            'data' => $resultat,
            'message' => 'Export en cours de generation.',
        ]);
    }
}
