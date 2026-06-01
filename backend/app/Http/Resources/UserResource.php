<?php

namespace App\Http\Resources;

use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_libelle' => Permissions::LIBELLES[$this->role] ?? $this->role,
            'telephone' => $this->telephone,
            'actif' => (bool) $this->actif,
            'derniere_connexion' => $this->derniere_connexion?->toISOString(),
            'cree_par' => $this->cree_par,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
