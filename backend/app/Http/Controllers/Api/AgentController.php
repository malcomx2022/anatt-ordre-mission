<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(
        private readonly AgentService $agentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $agents = $this->agentService->lister(
            $request->only(['search', 'categorie', 'service', 'actif']),
            $request->integer('per_page', 15),
        );

        return AgentResource::collection($agents)->response();
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = $this->agentService->creer($request->validated());

        return response()->json([
            'data' => new AgentResource($agent),
            'message' => 'Agent cree avec succes.',
        ], 201);
    }

    public function show(Agent $agent): JsonResponse
    {
        return response()->json([
            'data' => new AgentResource($this->agentService->afficher($agent)),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $agent = $this->agentService->modifier($agent, $request->validated());

        return response()->json([
            'data' => new AgentResource($agent),
            'message' => 'Agent mis a jour avec succes.',
        ]);
    }

    public function destroy(Agent $agent): JsonResponse
    {
        $this->agentService->desactiver($agent);

        return response()->json([
            'message' => 'Agent desactive avec succes.',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        $resultat = $this->agentService->importer($request->file('fichier'));

        return response()->json([
            'data' => $resultat,
            'message' => 'Import traite.',
        ]);
    }
}
