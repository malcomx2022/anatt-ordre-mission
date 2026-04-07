<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrdreMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => 'sometimes|exists:agents,id',
            'objet' => 'sometimes|string|max:500',
            'destination' => 'sometimes|string|max:255',
            'date_depart' => 'sometimes|date',
            'date_retour' => 'sometimes|date|after_or_equal:date_depart',
            'taux_id' => 'sometimes|exists:taux_per_diems,id',
        ];
    }
}
