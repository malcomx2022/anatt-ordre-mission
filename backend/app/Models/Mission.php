<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une mission (événement) regroupe N ordres de mission individuels
 * (participants + CAV), retranscrits depuis SEMO.
 */
class Mission extends Model
{
    use HasFactory;

    protected $table = 'missions';

    protected $fillable = [
        'objet',
        'destination',
        'zone_code',
        'date_depart',
        'date_retour',
        'imputation_budgetaire',
        'prise_en_charge_tiers',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'date_depart' => 'date',
            'date_retour' => 'date',
            'prise_en_charge_tiers' => 'boolean',
        ];
    }

    public function ordresMission(): HasMany
    {
        return $this->hasMany(OrdreMission::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ZoneGeographique::class, 'zone_code', 'code');
    }
}
