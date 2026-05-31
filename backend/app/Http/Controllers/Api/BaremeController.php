<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBaremeRequest;
use App\Http\Requests\UpdateBaremeRequest;
use App\Http\Resources\BaremeResource;
use App\Models\Bareme;
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

        return $this->created(new BaremeResource($bareme), 'Barème créé avec succès.');
    }

    public function update(UpdateBaremeRequest $request, Bareme $bareme): JsonResponse
    {
        $bareme = $this->baremeService->modifier($bareme, $request->validated());

        return $this->success(new BaremeResource($bareme), 'Barème mis à jour.');
    }

    public function destroy(Bareme $bareme): JsonResponse
    {
        $this->baremeService->supprimer($bareme);

        return $this->success(null, 'Barème supprimé.');
    }
}
