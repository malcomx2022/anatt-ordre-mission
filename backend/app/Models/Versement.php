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
     * payable : la validation est un prérequis au paiement.
     *
     * Verrou par tranche : dès que la mission a des états de paiement, le versement
     * n'est éligible que si l'état DE SA TRANCHE est validé par le Directeur
     * Général — la validation des avances n'ouvre PAS les reliquats. Une mission
     * sans aucun état relève de la reprise de données : le périmètre par OM fait
     * alors foi, comme avant l'introduction des états.
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
                ->where('statut', 'VALIDE')
                ->where(fn (Builder $qq) => $qq
                    ->whereDoesntHave('mission.etatsPaiement')
                    ->orWhereHas('mission.etatsPaiement', fn (Builder $e) => $e
                        ->where('tranche', $tranche)
                        ->where('statut', 'VALIDE'))))
            ->with('ordreMission.agent')
            ->orderBy('id');
    }
}
