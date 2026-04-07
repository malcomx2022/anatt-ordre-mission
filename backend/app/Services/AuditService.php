<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditService
{
    public function lister(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::with('user');

        if ($action = ($filters['action'] ?? null)) {
            $query->where('action', $action);
        }

        if ($userId = ($filters['user_id'] ?? null)) {
            $query->where('user_id', $userId);
        }

        if ($dateDebut = ($filters['date_debut'] ?? null)) {
            $query->where('created_at', '>=', $dateDebut);
        }

        if ($dateFin = ($filters['date_fin'] ?? null)) {
            $query->where('created_at', '<=', $dateFin);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function afficher(AuditLog $audit): AuditLog
    {
        return $audit->load('user');
    }

    public function enregistrer(User $user, string $action, string $cible, ?array $details = null, ?string $ip = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'cible' => $cible,
            'details' => $details,
            'ip_address' => $ip,
        ]);
    }
}
