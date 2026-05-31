<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditService
{
    /**
     * Trace un événement métier dans le journal d'audit (qui, quoi, quand, données).
     *
     * @param  array<string,mixed>  $donnees
     */
    public static function log(?User $user, string $action, string $entite, ?int $entiteId = null, array $donnees = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'donnees' => $donnees,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public function lister(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::with('user');

        if ($action = ($filters['action'] ?? null)) {
            $query->where('action', $action);
        }

        if ($entite = ($filters['entite'] ?? null)) {
            $query->where('entite', $entite);
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
}
