<?php

namespace App\Http\Requests;

use App\Models\Agent;
use App\Support\CategorieIndice;
use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Détermine automatiquement la catégorie depuis l'indice ou le salaire brut
     * lorsqu'elle n'est pas fournie explicitement, et ramène au vocabulaire
     * courant une catégorie transmise sous l'ancien code ORDRE_*.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('categorie') && ($this->filled('indice') || $this->filled('salaire_brut'))) {
            $this->merge([
                'categorie' => Agent::determinerCategorie(
                    $this->integer('indice') ?: null,
                    $this->integer('salaire_brut') ?: null,
                ),
            ]);

            return;
        }

        if ($normalisee = CategorieIndice::normaliser($this->input('categorie'))) {
            $this->merge(['categorie' => $normalisee]);
        }
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'prenoms' => 'required|string|max:255',
            'matricule' => 'required|string|max:50|unique:agents,matricule',
            'ifu' => 'nullable|string|max:50',
            'grade' => 'nullable|string|max:255',
            'indice' => 'nullable|integer|min:0',
            'salaire_brut' => 'nullable|integer|min:0',
            // Déduite de l'indice ou du salaire (prepareForValidation). Sans l'un ni
            // l'autre, le seul motif utile est « L'indice ou le salaire brut est
            // requis… » (withValidator) : réclamer la catégorie le masquerait, la SPA
            // n'affichant que le premier motif.
            'categorie' => 'required_with:indice,salaire_brut|nullable|'.CategorieIndice::regleIn(),
            // Téléphone national : 10 chiffres débutant par 01.
            'telephone' => ['required', 'string', 'regex:'.config('benin.telephone.regex'), 'unique:agents,telephone'],
            'operateur' => 'required|in:MTN,MOOV',
            'actif' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Au moins l'indice OU le salaire brut pour justifier/déterminer la catégorie.
            if (! $this->filled('indice') && ! $this->filled('salaire_brut')) {
                $validator->errors()->add('indice', "L'indice ou le salaire brut est requis pour déterminer la catégorie.");
            }

            // Cohérence numéro ↔ opérateur (préfixes MTN vs Moov).
            $tel = $this->input('telephone');
            $operateur = $this->input('operateur');
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
