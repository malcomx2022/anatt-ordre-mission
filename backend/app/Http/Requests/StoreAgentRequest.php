<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'matricule' => 'required|string|unique:agents,matricule',
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:agents,email',
            'telephone' => 'required|string|max:20',
            'numero_moov' => 'nullable|string|max:20',
            'numero_mtn' => 'nullable|string|max:20',
            'service' => 'required|string|max:255',
            'categorie' => 'required|in:A1,A2,B,C,D',
        ];
    }
}
