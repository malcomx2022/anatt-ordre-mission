<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneGeographique extends Model
{
    protected $table = 'zones_geographiques';

    protected $fillable = [
        'code',
        'libelle',
        'portee',
    ];

    /** La zone est-elle de portée extérieure (hors Bénin) ? */
    public function estExterieure(): bool
    {
        return $this->portee === 'EXTERIEUR';
    }
}
