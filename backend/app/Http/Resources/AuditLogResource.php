<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entite' => $this->entite,
            'entite_id' => $this->entite_id,
            'donnees' => $this->donnees,
            'ip_address' => $this->ip_address,
            'utilisateur' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->user?->role,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
