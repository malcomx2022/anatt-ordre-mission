<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Recalcule la catégorie dès que l'indice ou le salaire brut est modifié.
     *
     * La catégorie est une donnée DÉRIVÉE de l'indice/salaire, pas une saisie
     * libre : dès que l'un des deux change, on la recalcule et on l'impose —
     * y compris si l'ancienne catégorie figure dans le payload (le formulaire
     * d'édition la renvoie systématiquement). Sans cela, changer l'indice
     * laissait une catégorie périmée.
     *
     * La catégorie transmise n'est respectée que si NI l'indice NI le salaire
     * ne sont fournis (reprise de données historiques sans indice ni salaire).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('indice') && ! $this->filled('salaire_brut')) {
            return;
        }

        $agent = $this->route('agent');
        $indice = $this->has('indice') ? ($this->integer('indice') ?: null) : $agent->indice;
        $salaire = $this->has('salaire_brut') ? ($this->integer('salaire_brut') ?: null) : $agent->salaire_brut;

        $categorie = Agent::determinerCategorie($indice, $salaire);
        if ($categorie) {
            $this->merge(['categorie' => $categorie]);
        }
    }

    public function rules(): array
    {
        $agentId = $this->route('agent')->id;

        return [
            'nom' => 'sometimes|string|max:255',
            'prenoms' => 'sometimes|string|max:255',
            'matricule' => ['sometimes', 'string', 'max:50', Rule::unique('agents', 'matricule')->ignore($agentId)],
            'ifu' => 'nullable|string|max:50',
            'grade' => 'nullable|string|max:255',
            'indice' => 'nullable|integer|min:0',
            'salaire_brut' => 'nullable|integer|min:0',
            'categorie' => 'sometimes|in:ORDRE_1,ORDRE_2,ORDRE_3',
            'telephone' => ['sometimes', 'string', 'regex:'.config('benin.telephone.regex'), Rule::unique('agents', 'telephone')->ignore($agentId)],
            'operateur' => 'sometimes|in:MTN,MOOV',
            'actif' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $agent = $this->route('agent');
            $tel = $this->input('telephone', $agent->telephone);
            $operateur = $this->input('operateur', $agent->operateur);

            if ($tel && $operateur && ! Telephone::estCoherent($tel, $operateur)) {
                $deduit = Telephone::operateurPour($tel);
                $validator->errors()->add(
                    'telephone',
                    $deduit
                        ? "Le numéro correspond à l'opérateur {$deduit}, pas à {$operateur}."
                        : 'Le préfixe du numéro ne correspond à aucun opérateur connu.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le téléphone doit comporter 10 chiffres et débuter par 01.',
        ];
    }
}
