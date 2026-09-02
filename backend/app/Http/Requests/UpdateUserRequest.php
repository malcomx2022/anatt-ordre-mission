<?php

namespace App\Http\Requests;

use App\Support\PermissionRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', Password::defaults()],
            'telephone' => 'sometimes|nullable|string|max:30',
            'role' => ['sometimes', Rule::in(app(PermissionRepository::class)->codes())],
            'actif' => 'sometimes|boolean',
        ];
    }
}
