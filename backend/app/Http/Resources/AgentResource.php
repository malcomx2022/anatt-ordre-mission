<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'nom_complet' => $this->nom_complet,
            'matricule' => $this->matricule,
            'ifu' => $this->ifu,
            'grade' => $this->grade,
            'indice' => $this->indice,
            'salaire_brut' => $this->salaire_brut,
            'categorie' => $this->categorie,
            'telephone' => $this->telephone,
            'operateur' => $this->operateur,
            'actif' => $this->actif,
            'ordres_mission' => OrdreMissionResource::collection($this->whenLoaded('ordresMission')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
