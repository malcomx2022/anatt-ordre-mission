<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ordre_mission_id' => $this->ordre_mission_id,
            'lot_id' => $this->lot_id,
            'type' => $this->type,
            'montant' => $this->montant,
            'operateur' => $this->operateur,
            'telephone' => $this->telephone,
            'statut' => $this->statut,
            'date_paiement' => $this->date_paiement?->toISOString(),
            // Contexte bénéficiaire/mission : seulement quand l'OM est chargé
            // (ex. historique). Omis ailleurs pour ne pas déclencher de lazy-load.
            'reference_semo' => $this->whenLoaded('ordreMission', fn () => $this->ordreMission->reference_semo),
            'beneficiaire' => $this->whenLoaded('ordreMission', fn () => $this->ordreMission->agent?->nom_complet),
            'mission' => $this->whenLoaded('ordreMission', fn () => [
                'objet' => $this->ordreMission->mission?->objet,
                'destination' => $this->ordreMission->mission?->destination,
            ]),
        ];
    }
}
