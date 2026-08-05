<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBaremeRequest;
use App\Http\Requests\UpdateBaremeRequest;
use App\Http\Resources\BaremeResource;
use App\Models\Bareme;
use App\Services\AuditService;
use App\Services\BaremeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaremeController extends Controller
{
    public function __construct(
        private readonly BaremeService $baremeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $baremes = $this->baremeService->lister(
            $request->only(['categorie', 'zone_code', 'type_prise_en_charge']),
        );

        return $this->success(BaremeResource::collection($baremes));
    }

    public function show(Bareme $bareme): JsonResponse
    {
        return $this->success(new BaremeResource($bareme));
    }

    public function store(StoreBaremeRequest $request): JsonResponse
    {
        $bareme = $this->baremeService->creer($request->validated());

        AuditService::log($request->user(), 'CREATE_BAREME', 'bareme', $bareme->id, [
            'categorie' => $bareme->categorie,
            'zone_code' => $bareme->zone_code,
            'type_prise_en_charge' => $bareme->type_prise_en_charge,
            'taux_journalier' => $bareme->taux_journalier,
        ]);

        return $this->created(new BaremeResource($bareme), 'Barème créé avec succès.');
    }

    public function update(UpdateBaremeRequest $request, Bareme $bareme): JsonResponse
    {
        // Taux avant modification : l'audit d'un barème doit tracer le changement
        // de montant (ancien → nouveau), pas seulement la valeur finale.
        $ancienTaux = $bareme->taux_journalier;

        $bareme = $this->baremeService->modifier($bareme, $request->validated());

        AuditService::log($request->user(), 'UPDATE_BAREME', 'bareme', $bareme->id, [
            'categorie' => $bareme->categorie,
            'zone_code' => $bareme->zone_code,
            'type_prise_en_charge' => $bareme->type_prise_en_charge,
            'ancien_taux' => $ancienTaux,
            'nouveau_taux' => $bareme->taux_journalier,
        ]);

        return $this->success(new BaremeResource($bareme), 'Barème mis à jour.');
    }

    public function destroy(Request $request, Bareme $bareme): JsonResponse
    {
        // Instantané avant suppression : après coup, l'id et les attributs sont perdus.
        $donnees = [
            'categorie' => $bareme->categorie,
            'zone_code' => $bareme->zone_code,
            'type_prise_en_charge' => $bareme->type_prise_en_charge,
            'taux_journalier' => $bareme->taux_journalier,
        ];
        $baremeId = $bareme->id;

        $this->baremeService->supprimer($bareme);

        AuditService::log($request->user(), 'DELETE_BAREME', 'bareme', $baremeId, $donnees);

        return $this->success(null, 'Barème supprimé.');
    }
}
