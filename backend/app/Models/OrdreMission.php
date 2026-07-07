<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un ordre de mission = 1 enregistrement = 1 bénéficiaire (mono-bénéficiaire),
 * retranscrit depuis SEMO via sa reference_semo (clé naturelle unique).
 */
class OrdreMission extends Model
{
    use HasFactory;

    protected $table = 'ordre_missions';

    protected $fillable = [
        'reference_semo',
        'mission_id',
        'agent_id',
        'role',                 // PARTICIPANT | CAV
        'categorie',            // ORDRE_1 | ORDRE_2 | ORDRE_3 (reprise SEMO)
        'zone_code',
        'type_prise_en_charge',
        'nb_jours',
        'lignes_per_diem',      // répartition jours par type [{type, taux, nb_jours, montant}]
        'montant_transport',
        'montant_total',
        'avance',
        'reliquat',
        'statut',               // BROUILLON|IMPORTE|EN_ATTENTE|VALIDE|REJETE|SOLDE|ANNULE
        'conducteur_a_bord',    // mention logistique uniquement
        'reference_autorisation',
        'moyen_transport',
        'commentaire_rejet',
    ];

    protected function casts(): array
    {
        return [
            'nb_jours' => 'integer',
            'lignes_per_diem' => 'array',
            'montant_transport' => 'integer',
            'montant_total' => 'integer',
            'avance' => 'integer',
            'reliquat' => 'integer',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function versements(): HasMany
    {
        return $this->hasMany(Versement::class);
    }

    /**
     * Recherche sur reference_semo, statut, et l'agent associé (nom, prénoms, matricule).
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $terms = array_filter(explode(' ', trim($search)));

        if (empty($terms)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($terms) {
            foreach ($terms as $term) {
                $like = "%{$term}%";
                $q->where(function (Builder $inner) use ($like) {
                    $inner->where('reference_semo', 'like', $like)
                        ->orWhere('statut', 'like', $like)
                        ->orWhereHas('agent', function (Builder $agentQuery) use ($like) {
                            $agentQuery->where('nom', 'like', $like)
                                ->orWhere('prenoms', 'like', $like)
                                ->orWhere('matricule', 'like', $like);
                        });
                });
            }
        });
    }
}
