<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TauxPerDiem extends Model
{
    use HasFactory;

    protected $fillable = [
        'categorie',
        'destination_type',
        'montant_journalier',
        'annee',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'montant_journalier' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }
}
