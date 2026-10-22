<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot de paiement = 1 mission × 1 tranche [AVANCE|RELIQUAT] × 1 opérateur [MTN|MOOV]
 * × 1 séquence (1 = lot initial, 2.. = lot complémentaire après confirmation).
 * (Généré en phase Paiements ; modèle déclaré ici pour les relations.)
 */
class LotPaiement extends Model
{
    protected $table = 'lots_paiement';

    protected $fillable = [
        'mission_id',
        'tranche',
        'operateur',
        'sequence',
        'statut',
        'nom_fichier',
        'nb_lignes',
        'montant_total',
        'genere_le',
        'genere_par',
        'confirme_le',
        'confirme_par',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'nb_lignes' => 'integer',
            'montant_total' => 'integer',
            'genere_le' => 'datetime',
            'confirme_le' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function versements(): HasMany
    {
        return $this->hasMany(Versement::class, 'lot_id');
    }
}
