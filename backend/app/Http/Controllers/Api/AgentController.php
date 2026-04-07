<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Agent::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($categorie = $request->get('categorie')) {
            $query->where('categorie', $categorie);
        }

        if ($service = $request->get('service')) {
            $query->where('service', $service);
        }

        if ($request->has('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $agents = $query->orderBy('nom')->paginate($request->get('per_page', 15));

        return AgentResource::collection($agents)->response();
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = Agent::create($request->validated());

        return response()->json([
            'data' => new AgentResource($agent),
            'message' => 'Agent cree avec succes.',
        ], 201);
    }

    public function show(Agent $agent): JsonResponse
    {
        $agent->load('ordresMission');

        return response()->json([
            'data' => new AgentResource($agent),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $agent->update($request->validated());

        return response()->json([
            'data' => new AgentResource($agent),
            'message' => 'Agent mis a jour avec succes.',
        ]);
    }

    public function destroy(Agent $agent): JsonResponse
    {
        $agent->update(['actif' => false]);

        return response()->json([
            'message' => 'Agent desactive avec succes.',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        // Import logic will be implemented with Maatwebsite/Excel
        return response()->json([
            'message' => 'Import en cours de traitement.',
        ]);
    }
}
