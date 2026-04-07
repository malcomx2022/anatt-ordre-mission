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
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => $this->nom_complet,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'numero_moov' => $this->numero_moov,
            'numero_mtn' => $this->numero_mtn,
            'service' => $this->service,
            'categorie' => $this->categorie,
            'actif' => $this->actif,
            'ordres_mission' => OrdreMissionResource::collection($this->whenLoaded('ordresMission')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
