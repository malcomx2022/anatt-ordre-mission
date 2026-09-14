<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Support\CategorieIndice;
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
     * Recalcule systématiquement la catégorie si l'indice ou le salaire est modifié,
     * même si le formulaire envoie l'ancienne catégorie.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('indice') || $this->has('salaire_brut')) {
            $agent = $this->route('agent');
            $indice = $this->has('indice') ? ($this->integer('indice') ?: null) : $agent->indice;
            $salaire = $this->has('salaire_brut') ? ($this->integer('salaire_brut') ?: null) : $agent->salaire_brut;

            $categorie = Agent::determinerCategorie($indice, $salaire);
            if ($categorie) {
                $this->merge(['categorie' => $categorie]);

                return;
            }
        }

        // Catégorie transmise sous l'ancien code ORDRE_* : on la traduit.
        if ($normalisee = CategorieIndice::normaliser($this->input('categorie'))) {
            $this->merge(['categorie' => $normalisee]);
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
            'categorie' => 'sometimes|'.CategorieIndice::regleIn(),
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
