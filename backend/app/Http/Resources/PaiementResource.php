<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ordre_mission_id' => $this->ordre_mission_id,
            'agent_id' => $this->agent_id,
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'ordre_mission' => new OrdreMissionResource($this->whenLoaded('ordreMission')),
            'operateur' => $this->operateur,
            'numero_destinataire' => $this->numero_destinataire,
            'montant' => $this->montant,
            'statut' => $this->statut,
            'reference_transaction' => $this->reference_transaction,
            'fichier_virement' => $this->fichier_virement,
            'date_virement' => $this->date_virement?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
