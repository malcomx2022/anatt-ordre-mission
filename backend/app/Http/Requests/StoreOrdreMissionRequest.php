<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdreMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => 'required|exists:agents,id',
            'objet' => 'required|string|max:500',
            'destination' => 'required|string|max:255',
            'date_depart' => 'required|date|after_or_equal:today',
            'date_retour' => 'required|date|after_or_equal:date_depart',
            'taux_id' => 'required|exists:taux_per_diems,id',
        ];
    }
}
