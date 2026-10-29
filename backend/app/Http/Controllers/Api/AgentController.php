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
            $request->only(['search', 'categorie', 'operateur', 'actif']),
            $this->getPerPage($request),
        );

        return AgentResource::collection($agents)->response();
    }

    /** Compteurs globaux (tout le jeu filtré) pour les cartes de l'écran Personnel. */
    public function stats(Request $request): JsonResponse
    {
        return $this->success($this->agentService->statistiques(
            $request->only(['search', 'categorie', 'operateur', 'actif']),
        ));
    }

    /**
     * Autocomplétion du wizard OM : recherche par nom / matricule / IFU.
     */
    public function recherche(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1']);

        $agents = $this->agentService->rechercher($request->string('q'));

        return $this->success(AgentResource::collection($agents));
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = $this->agentService->creer($request->validated());

        return $this->created(new AgentResource($agent), 'Agent créé avec succès.');
    }

    public function show(Agent $agent): JsonResponse
    {
        return $this->success(new AgentResource($this->agentService->afficher($agent)));
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $agent = $this->agentService->modifier($agent, $request->validated());

        return $this->success(new AgentResource($agent), 'Agent mis à jour avec succès.');
    }

    public function destroy(Agent $agent): JsonResponse
    {
        $this->agentService->desactiver($agent);

        return $this->success(null, 'Agent désactivé avec succès.');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        $resultat = $this->agentService->importer($request->file('fichier'));

        // Échec : aucune ligne importée (rollback) -> 422 avec rapport ligne à ligne.
        if (! empty($resultat['rollback'])) {
            return $this->unprocessable(
                'Import annulé : '.count($resultat['erreurs']).' ligne(s) en erreur. Aucun agent enregistré.',
                $resultat['erreurs'],
            );
        }

        return $this->success($resultat, "{$resultat['importes']} agent(s) importé(s) avec succès.");
    }
}
