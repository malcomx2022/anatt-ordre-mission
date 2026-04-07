<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $agentId = $this->route('agent')->id;

        return [
            'matricule' => ['sometimes', 'string', Rule::unique('agents', 'matricule')->ignore($agentId)],
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('agents', 'email')->ignore($agentId)],
            'telephone' => 'sometimes|string|max:20',
            'numero_moov' => 'nullable|string|max:20',
            'numero_mtn' => 'nullable|string|max:20',
            'service' => 'sometimes|string|max:255',
            'categorie' => 'sometimes|in:A1,A2,B,C,D',
            'actif' => 'sometimes|boolean',
        ];
    }
}
