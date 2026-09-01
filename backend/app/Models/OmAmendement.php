<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un amendement d'ordre de mission : changement du nombre de jours facturés
 * (rallonge ou réduction) après retranscription. Journal pour audit et historique.
 * Cf. docs/amendement-om.md.
 */
class OmAmendement extends Model
{
    use HasFactory;

    protected $table = 'om_amendements';

    protected $fillable = [
        'ordre_mission_id',
        'nb_jours_avant',
        'nb_jours_apres',
        'lignes_avant',
        'montant_total_avant',
        'montant_total_apres',
        'delta_montant',
        'motif',
        'amende_par',
        'amende_par_nom',
        'amende_le',
    ];

    protected function casts(): array
    {
        return [
            'nb_jours_avant' => 'integer',
            'nb_jours_apres' => 'integer',
            'lignes_avant' => 'array',
            'montant_total_avant' => 'integer',
            'montant_total_apres' => 'integer',
            'delta_montant' => 'integer',
            'amende_le' => 'datetime',
        ];
    }

    public function ordreMission(): BelongsTo
    {
        return $this->belongsTo(OrdreMission::class);
    }
}
