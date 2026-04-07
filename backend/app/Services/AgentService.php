<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class AgentService
{
    public function lister(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Agent::query();

        if ($search = ($filters['search'] ?? null)) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($categorie = ($filters['categorie'] ?? null)) {
            $query->where('categorie', $categorie);
        }

        if ($service = ($filters['service'] ?? null)) {
            $query->where('service', $service);
        }

        if (isset($filters['actif'])) {
            $query->where('actif', filter_var($filters['actif'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('nom')->paginate($perPage);
    }

    public function creer(array $data): Agent
    {
        return Agent::create($data);
    }

    public function afficher(Agent $agent): Agent
    {
        return $agent->load('ordresMission');
    }

    public function modifier(Agent $agent, array $data): Agent
    {
        $agent->update($data);

        return $agent->fresh();
    }

    public function desactiver(Agent $agent): void
    {
        $agent->update(['actif' => false]);
    }

    public function importer(UploadedFile $fichier): array
    {
        // Import logic sera implementee avec Maatwebsite/Excel
        // Pour l'instant retourne un resultat placeholder
        return [
            'importes' => 0,
            'ignores' => 0,
            'erreurs' => [],
        ];
    }
}
