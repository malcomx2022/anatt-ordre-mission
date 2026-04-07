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
        return $this->success($this->tauxService->listerActifs());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie' => 'required|string',
            'destination_type' => 'required|in:LOCALE,REGIONALE,INTERNATIONALE',
            'montant_journalier' => 'required|numeric|min:0',
            'annee' => 'required|integer|min:2020',
        ]);

        return $this->created($this->tauxService->creer($data), 'Taux cree avec succes.');
    }

    public function update(Request $request, TauxPerDiem $taux): JsonResponse
    {
        $data = $request->validate([
            'montant_journalier' => 'sometimes|numeric|min:0',
            'actif' => 'sometimes|boolean',
        ]);

        return $this->success($this->tauxService->modifier($taux, $data), 'Taux mis a jour.');
    }
}
