<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotPaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mission_id' => $this->mission_id,
            'mission' => new MissionResource($this->whenLoaded('mission')),
            'tranche' => $this->tranche,
            'operateur' => $this->operateur,
            // 1 = lot initial, 2.. = lot complémentaire (après confirmation du précédent).
            'sequence' => $this->sequence,
            'statut' => $this->statut,
            'nom_fichier' => $this->nom_fichier ? basename($this->nom_fichier) : null,
            'nb_lignes' => $this->nb_lignes,
            'montant_total' => $this->montant_total,
            'genere_le' => $this->genere_le?->toISOString(),
            'genere_par' => $this->genere_par,
            'confirme_le' => $this->confirme_le?->toISOString(),
            'confirme_par' => $this->confirme_par,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
