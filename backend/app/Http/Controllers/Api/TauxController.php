<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TauxPerDiem;
use App\Services\TauxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TauxController extends Controller
{
    public function __construct(
        private readonly TauxService $tauxService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->tauxService->listerActifs(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie' => 'required|string',
            'destination_type' => 'required|in:LOCALE,REGIONALE,INTERNATIONALE',
            'montant_journalier' => 'required|numeric|min:0',
            'annee' => 'required|integer|min:2020',
        ]);

        $taux = $this->tauxService->creer($data);

        return response()->json([
            'data' => $taux,
            'message' => 'Taux cree avec succes.',
        ], 201);
    }

    public function update(Request $request, TauxPerDiem $taux): JsonResponse
    {
        $data = $request->validate([
            'montant_journalier' => 'sometimes|numeric|min:0',
            'actif' => 'sometimes|boolean',
        ]);

        $taux = $this->tauxService->modifier($taux, $data);

        return response()->json([
            'data' => $taux,
            'message' => 'Taux mis a jour.',
        ]);
    }
}
