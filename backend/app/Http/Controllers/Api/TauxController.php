<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TauxPerDiem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TauxController extends Controller
{
    public function index(): JsonResponse
    {
        $taux = TauxPerDiem::where('actif', true)
            ->orderBy('categorie')
            ->orderBy('destination_type')
            ->get();

        return response()->json(['data' => $taux]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie' => 'required|string',
            'destination_type' => 'required|in:LOCALE,REGIONALE,INTERNATIONALE',
            'montant_journalier' => 'required|numeric|min:0',
            'annee' => 'required|integer|min:2020',
        ]);

        $taux = TauxPerDiem::create([...$data, 'actif' => true]);

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

        $taux->update($data);

        return response()->json([
            'data' => $taux,
            'message' => 'Taux mis a jour.',
        ]);
    }
}
