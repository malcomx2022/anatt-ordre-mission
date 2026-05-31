<?php

namespace App\Models;

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
}
