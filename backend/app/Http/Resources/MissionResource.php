<?php

namespace App\Http\Resources;

use App\Models\EtatPaiement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'objet' => $this->objet,
            'destination' => $this->destination,
            'zone_code' => $this->zone_code,
            'date_depart' => $this->date_depart?->toDateString(),
            'date_retour' => $this->date_retour?->toDateString(),
            'imputation_budgetaire' => $this->imputation_budgetaire,
            'prise_en_charge_tiers' => $this->prise_en_charge_tiers,
            'observations' => $this->observations,
            'ordres_mission' => OrdreMissionResource::collection($this->whenLoaded('ordresMission')),
            // État des avances de la mission (premier document du circuit).
            'etat_paiement' => $this->whenLoaded('etatPaiement', fn () => $this->etatPaiement
                ? [
                    'id' => $this->etatPaiement->id,
                    'tranche' => $this->etatPaiement->tranche,
                    'statut' => $this->etatPaiement->statut,
                    // Sans préfixe : les écrans posent eux-mêmes le « N° ».
                    'numero' => EtatPaiement::normaliserNumero($this->etatPaiement->numero),
                    'valide' => $this->etatPaiement->estValide(),
                ]
                : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
