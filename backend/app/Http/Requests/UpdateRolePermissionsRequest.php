<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'accès est déjà verrouillé par le middleware permission:roles.gerer.
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::TOUTES)],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'permissions.present' => 'La liste des permissions est requise (éventuellement vide).',
            'permissions.*.in' => 'Clé de permission inconnue.',
        ];
    }
}
