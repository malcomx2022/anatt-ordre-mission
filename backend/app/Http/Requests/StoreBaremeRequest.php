<?php

namespace App\Http\Requests;

use App\Models\Bareme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBaremeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie' => 'required|in:ORDRE_1,ORDRE_2,ORDRE_3',
            'zone_code' => 'required|in:INTERIEUR,EXT_ZONE_I,EXT_ZONE_II,EXT_ZONE_III',
            'type_prise_en_charge' => 'required|in:UN_REPAS,DEUX_REPAS,JOURNEE_COMPLETE,LOGE_NOURRI,LOGE_SEUL,NOURRI_SEUL',
            'taux_journalier' => 'required|integer|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $zone = $this->input('zone_code');
            $type = $this->input('type_prise_en_charge');

            // Contrainte applicative : repas ⇒ INTERIEUR ; hébergement ⇒ EXTERIEUR.
            if ($zone && $type && ! Bareme::typeCompatible($zone, $type)) {
                $validator->errors()->add(
                    'type_prise_en_charge',
                    "Le type « {$type} » est incompatible avec la zone {$zone}.",
                );
            }

            // Unicité du triplet (catégorie, zone, type).
            $existe = Bareme::where('categorie', $this->input('categorie'))
                ->where('zone_code', $zone)
                ->where('type_prise_en_charge', $type)
                ->exists();

            if ($existe) {
                $validator->errors()->add('type_prise_en_charge', 'Un barème existe déjà pour ce triplet (catégorie, zone, type).');
            }
        });
    }
}
