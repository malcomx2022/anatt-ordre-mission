<?php

namespace App\Http\Requests;

use App\Models\OrdreMission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Amendement d'un OM : nouveau nombre de jours (mode simple) OU répartition
 * détaillée par lignes, avec motif obligatoire. La zone et la catégorie de l'OM
 * ne changent pas ; on vérifie la compatibilité type ↔ portée de zone.
 * Cf. docs/amendement-om.md.
 */
class AmenderOrdreMissionRequest extends FormRequest
{
    private const TYPES_INTERIEUR = ['UN_REPAS', 'DEUX_REPAS', 'JOURNEE_COMPLETE'];

    private const TYPES_EXTERIEUR = ['JOURNEE_COMPLETE', 'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Type vide éventuel dans une ligne : neutralisé pour le message d'erreur.
        if (($this->input('type_prise_en_charge')) === '') {
            $this->merge(['type_prise_en_charge' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'motif' => 'required|string|min:10|max:1000',
            // Mode simple : nouveau nombre de jours. Requis sauf si des lignes sont fournies.
            'nb_jours' => 'nullable|required_without:lignes|integer|min:1',
            // Répartition détaillée (état ANaTT).
            'lignes' => 'nullable|array',
            'lignes.*.type' => 'required_with:lignes|in:UN_REPAS,DEUX_REPAS,JOURNEE_COMPLETE,LOGE_NOURRI,LOGE_SEUL,NOURRI_SEUL',
            'lignes.*.nb_jours' => 'required_with:lignes|integer|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ordre = $this->route('ordre');
            if (! $ordre instanceof OrdreMission) {
                return;
            }

            $typesAutorises = $ordre->zone_code === 'INTERIEUR' ? self::TYPES_INTERIEUR : self::TYPES_EXTERIEUR;

            foreach ((array) $this->input('lignes', []) as $j => $ligne) {
                $t = $ligne['type'] ?? null;
                if ($t && ! in_array($t, $typesAutorises, true)) {
                    $validator->errors()->add(
                        "lignes.{$j}.type",
                        "Le type « {$t} » est incompatible avec la zone {$ordre->zone_code}.",
                    );
                }
            }
        });
    }
}
