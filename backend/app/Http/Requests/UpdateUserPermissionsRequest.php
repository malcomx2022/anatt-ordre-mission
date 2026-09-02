<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Accès déjà verrouillé par le middleware permission:roles.gerer.
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'granted' => ['present', 'array'],
            'granted.*' => ['string', Rule::in(Permissions::TOUTES)],
            'revoked' => ['present', 'array'],
            'revoked.*' => ['string', Rule::in(Permissions::TOUTES)],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'granted.present' => 'La liste des permissions accordées est requise (éventuellement vide).',
            'revoked.present' => 'La liste des permissions retirées est requise (éventuellement vide).',
            'granted.*.in' => 'Clé de permission inconnue.',
            'revoked.*.in' => 'Clé de permission inconnue.',
        ];
    }
}
