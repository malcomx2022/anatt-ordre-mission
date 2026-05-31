<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBaremeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Seul le montant est modifiable : la clé naturelle (catégorie, zone, type) est
     * immuable (créer/supprimer une ligne pour la changer).
     */
    public function rules(): array
    {
        return [
            'taux_journalier' => 'required|integer|min:0',
        ];
    }
}
