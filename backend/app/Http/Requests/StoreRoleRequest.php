<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Accès déjà verrouillé par le middleware permission:roles.gerer.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            // Code façon constante : MAJUSCULES, chiffres et underscores.
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'libelle' => ['required', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::TOUTES)],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code doit être en MAJUSCULES (lettres, chiffres, underscore), ex. CAISSIER_REGIONAL.',
            'permissions.*.in' => 'Clé de permission inconnue.',
        ];
    }
}
