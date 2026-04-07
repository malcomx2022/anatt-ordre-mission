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
            'numero' => $this->numero,
            'agent_id' => $this->agent_id,
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'objet' => $this->objet,
            'destination' => $this->destination,
            'date_depart' => $this->date_depart?->toDateString(),
            'date_retour' => $this->date_retour?->toDateString(),
            'nb_jours' => $this->nb_jours,
            'taux_id' => $this->taux_id,
            'taux' => $this->whenLoaded('taux'),
            'montant_per_diem' => $this->montant_per_diem,
            'statut' => $this->statut,
            'commentaire_rejet' => $this->commentaire_rejet,
            'valide_par_chef' => $this->valide_par_chef,
            'valide_par_dg' => $this->valide_par_dg,
            'paiements' => PaiementResource::collection($this->whenLoaded('paiements')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
