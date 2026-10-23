<?php

namespace App\Http\Resources;

use App\Support\Workflow;
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
            'lignes_per_diem' => $this->lignes_per_diem ?? [],
            'montant_transport' => $this->montant_transport,
            'montant_total' => $this->montant_total,
            'avance' => $this->avance,
            'avance_engagee' => $this->avance_engagee,
            'reliquat' => $this->reliquat,
            'statut' => $this->statut,
            'conducteur_a_bord' => $this->conducteur_a_bord,
            'reference_autorisation' => $this->reference_autorisation,
            'moyen_transport' => $this->moyen_transport,
            'commentaire_rejet' => $this->commentaire_rejet,
            'versements' => VersementResource::collection($this->whenLoaded('versements')),
            // Journal des amendements (rallonge / réduction des jours facturés).
            'amendements' => $this->whenLoaded('amendements', fn () => $this->amendements
                // Le dernier en tête ; à la même seconde, l'ordre de création départage.
                ->sortBy([['amende_le', 'desc'], ['id', 'desc']])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'nb_jours_avant' => $a->nb_jours_avant,
                    'nb_jours_apres' => $a->nb_jours_apres,
                    'montant_total_avant' => $a->montant_total_avant,
                    'montant_total_apres' => $a->montant_total_apres,
                    'delta_montant' => $a->delta_montant,
                    'motif' => $a->motif,
                    'amende_par_nom' => $a->amende_par_nom,
                    'amende_le' => $a->amende_le?->toISOString(),
                ])->values()),
            // ── Signature de validation (OM par OM, une étape) ──
            'verification' => $this->etape($this->verifie_nom, $this->verifie_le, $this->verifie_fonction, $this->signature_verif_hash),
            'visa_daf' => $this->etape($this->visa_daf_nom, $this->visa_daf_le, $this->visa_daf_fonction, $this->signature_daf_hash),
            'validation_dg' => $this->etape($this->valide_dg_nom, $this->valide_dg_le, $this->valide_dg_fonction, $this->signature_dg_hash),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Étape franchie du circuit, ou null. Le nom et la fonction sont les
     * instantanés pris au moment de la signature, jamais l'état courant du compte.
     *
     * @return array{nom:string, fonction:?string, date:?string, code:?string}|null
     */
    private function etape(?string $nom, mixed $date, ?string $fonction, ?string $hash): ?array
    {
        if (empty($nom)) {
            return null;
        }

        return [
            'nom' => $nom,
            'fonction' => $fonction,
            'date' => $date?->toISOString(),
            'code' => Workflow::codeVerification($hash),
        ];
    }
}
