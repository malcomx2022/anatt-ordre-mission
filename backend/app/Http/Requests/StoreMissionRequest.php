<?php

namespace App\Http\Requests;

use App\Support\CategorieIndice;
use App\Support\ZoneResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMissionRequest extends FormRequest
{
    /** Types de prise en charge autorisés selon la portée de la zone. */
    private const TYPES_INTERIEUR = ['UN_REPAS', 'DEUX_REPAS', 'JOURNEE_COMPLETE'];

    private const TYPES_EXTERIEUR = ['JOURNEE_COMPLETE', 'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL'];

    public function authorize(): bool
    {
        return true;
    }

    /** Déduit la zone depuis la destination si elle n'est pas fournie explicitement. */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('zone_code') && $this->filled('destination')) {
            $zone = ZoneResolver::deduire($this->input('destination'));
            if ($zone) {
                $this->merge(['zone_code' => $zone]);
            }
        }

        // Mode « répartition détaillée » : l'UI envoie les lignes et laisse le
        // type de prise en charge à '' (chaîne vide). On le normalise à null pour
        // que la règle conditionnelle required_without:lignes s'applique
        // proprement (sinon le `in:` rejette la chaîne vide).
        $ordres = $this->input('ordres');
        if (is_array($ordres)) {
            foreach ($ordres as $i => $ordre) {
                if (($ordre['type_prise_en_charge'] ?? null) === '') {
                    $ordres[$i]['type_prise_en_charge'] = null;
                }

                // Catégorie transmise sous l'ancien code ORDRE_* : on la traduit.
                if ($normalisee = CategorieIndice::normaliser($ordre['categorie'] ?? null)) {
                    $ordres[$i]['categorie'] = $normalisee;
                }
            }
            $this->merge(['ordres' => $ordres]);
        }
    }

    public function rules(): array
    {
        return [
            'objet' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'zone_code' => 'nullable|in:INTERIEUR,EXT_ZONE_I,EXT_ZONE_II,EXT_ZONE_III',
            'date_depart' => 'required|date',
            'date_retour' => 'required|date|after_or_equal:date_depart',
            'imputation_budgetaire' => 'nullable|string|max:255',
            'prise_en_charge_tiers' => 'sometimes|boolean',
            'observations' => 'nullable|string',

            // Au moins un bénéficiaire (participant ou CAV).
            'ordres' => 'required|array|min:1',
            'ordres.*.reference_semo' => 'required|string|max:255|distinct|unique:ordre_missions,reference_semo',
            'ordres.*.agent_id' => 'required|integer|exists:agents,id',
            'ordres.*.role' => 'required|in:PARTICIPANT,CAV',
            'ordres.*.categorie' => 'nullable|'.CategorieIndice::regleIn(),
            // Requis uniquement en mode simple : en répartition détaillée, ce sont
            // les `lignes` qui portent les types (le type est alors déduit).
            'ordres.*.type_prise_en_charge' => 'nullable|required_without:ordres.*.lignes|in:UN_REPAS,DEUX_REPAS,JOURNEE_COMPLETE,LOGE_NOURRI,LOGE_SEUL,NOURRI_SEUL',
            'ordres.*.nb_jours' => 'nullable|integer|min:1',
            // Répartition détaillée des jours par type (état ANaTT). Optionnelle :
            // si absente, per diem = type_prise_en_charge × nb_jours.
            'ordres.*.lignes' => 'nullable|array',
            'ordres.*.lignes.*.type' => 'required_with:ordres.*.lignes|in:UN_REPAS,DEUX_REPAS,JOURNEE_COMPLETE,LOGE_NOURRI,LOGE_SEUL,NOURRI_SEUL',
            'ordres.*.lignes.*.nb_jours' => 'required_with:ordres.*.lignes|integer|min:0',
            // Montant de transport (colonne TRANSPORT), ajouté au total.
            'ordres.*.montant_transport' => 'nullable|integer|min:0',
            'ordres.*.conducteur_a_bord' => 'nullable|string|max:255',
            'ordres.*.reference_autorisation' => 'nullable|string|max:255',
            'ordres.*.moyen_transport' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $zone = $this->input('zone_code');

            // La zone doit être déductible ou fournie.
            if (! $zone) {
                $validator->errors()->add(
                    'zone_code',
                    "Zone indéterminable pour la destination « {$this->input('destination')} » : précisez zone_code.",
                );

                return;
            }

            // Cohérence type de prise en charge ↔ portée de la zone.
            $typesAutorises = $zone === 'INTERIEUR' ? self::TYPES_INTERIEUR : self::TYPES_EXTERIEUR;

            foreach ((array) $this->input('ordres', []) as $i => $ordre) {
                $type = $ordre['type_prise_en_charge'] ?? null;
                if ($type && ! in_array($type, $typesAutorises, true)) {
                    $validator->errors()->add(
                        "ordres.{$i}.type_prise_en_charge",
                        "Le type « {$type} » est incompatible avec la zone {$zone}.",
                    );
                }

                // Chaque ligne détaillée doit aussi être compatible avec la zone.
                foreach ((array) ($ordre['lignes'] ?? []) as $j => $ligne) {
                    $lt = $ligne['type'] ?? null;
                    if ($lt && ! in_array($lt, $typesAutorises, true)) {
                        $validator->errors()->add(
                            "ordres.{$i}.lignes.{$j}.type",
                            "Le type « {$lt} » est incompatible avec la zone {$zone}.",
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'ordres.*.reference_semo.unique' => 'La référence SEMO :input existe déjà (doublon interdit).',
            'ordres.*.reference_semo.distinct' => 'La référence SEMO :input est répétée dans la requête.',
        ];
    }
}
