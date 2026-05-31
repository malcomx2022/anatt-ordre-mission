<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdreMissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_semo' => $this->reference_semo,
            'mission_id' => $this->mission_id,
            'mission' => new MissionResource($this->whenLoaded('mission')),
            'agent_id' => $this->agent_id,
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'role' => $this->role,
            'categorie' => $this->categorie,
            'zone_code' => $this->zone_code,
            'type_prise_en_charge' => $this->type_prise_en_charge,
            'nb_jours' => $this->nb_jours,
            'montant_total' => $this->montant_total,
            'avance' => $this->avance,
            'reliquat' => $this->reliquat,
            'statut' => $this->statut,
            'conducteur_a_bord' => $this->conducteur_a_bord,
            'reference_autorisation' => $this->reference_autorisation,
            'moyen_transport' => $this->moyen_transport,
            'commentaire_rejet' => $this->commentaire_rejet,
            'versements' => VersementResource::collection($this->whenLoaded('versements')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
