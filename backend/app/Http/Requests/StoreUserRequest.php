<?php

namespace App\Http\Requests;

use App\Support\PermissionRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation portée par le middleware + le contrôleur
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', Password::defaults()],
            'telephone' => 'nullable|string|max:30',
            'role' => ['required', Rule::in(app(PermissionRepository::class)->codes())],
            'actif' => 'sometimes|boolean',
        ];
    }
}
