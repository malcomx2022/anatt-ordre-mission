<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Versement extends Model
{
    use HasFactory;

    protected $table = 'versements';

    protected $fillable = [
        'ordre_mission_id',
        'lot_id',
        'type',          // AVANCE | RELIQUAT
        'montant',
        'operateur',     // MTN | MOOV
        'telephone',
        'statut',        // EN_ATTENTE | GENERE | CONFIRME | ANNULE
        'date_paiement',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_paiement' => 'datetime',
        ];
    }

    public function ordreMission(): BelongsTo
    {
        return $this->belongsTo(OrdreMission::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotPaiement::class, 'lot_id');
    }

    /**
     * Versements éligibles à un lot : mission × tranche × opérateur, statut != CONFIRME,
     * dont l'OM est VALIDE. Un OM non validé (IMPORTE) ou rejeté/annulé n'est jamais
     * payable : la validation par le CONTROLEUR est un prérequis au paiement.
     * Agent eager-loadé pour la génération.
     */
    public function scopeEligiblesLot(Builder $query, int $missionId, string $tranche, string $operateur): Builder
    {
        return $query
            ->where('type', $tranche)
            ->where('operateur', $operateur)
            ->where('statut', '!=', 'CONFIRME')
            ->whereHas('ordreMission', fn (Builder $q) => $q
                ->where('mission_id', $missionId)
                ->where('statut', 'VALIDE'))
            ->with('ordreMission.agent')
            ->orderBy('id');
    }
}
