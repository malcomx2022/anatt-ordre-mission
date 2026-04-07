<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrdreMission extends Model
{
    use HasFactory;

    protected $table = 'ordre_missions';

    protected $fillable = [
        'numero',
        'agent_id',
        'objet',
        'destination',
        'date_depart',
        'date_retour',
        'nb_jours',
        'taux_id',
        'montant_per_diem',
        'statut',
        'commentaire_rejet',
        'valide_par_chef',
        'valide_par_dg',
    ];

    protected function casts(): array
    {
        return [
            'date_depart' => 'date',
            'date_retour' => 'date',
            'montant_per_diem' => 'decimal:2',
        ];
    }

    /**
     * Recherche full-text sur les ordres de mission.
     * Cherche dans: numero, objet, destination, commentaire_rejet,
     * et dans l'agent associe (nom, prenom, matricule).
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
                    $inner->where('numero', 'like', $like)
                        ->orWhere('objet', 'like', $like)
                        ->orWhere('destination', 'like', $like)
                        ->orWhere('statut', 'like', $like)
                        ->orWhere('commentaire_rejet', 'like', $like)
                        ->orWhereHas('agent', function (Builder $agentQuery) use ($like) {
                            $agentQuery->where('nom', 'like', $like)
                                ->orWhere('prenom', 'like', $like)
                                ->orWhere('matricule', 'like', $like);
                        });
                });
            }
        });
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function taux()
    {
        return $this->belongsTo(TauxPerDiem::class, 'taux_id');
    }

    public function valideurChef()
    {
        return $this->belongsTo(User::class, 'valide_par_chef');
    }

    public function valideurDG()
    {
        return $this->belongsTo(User::class, 'valide_par_dg');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    public static function generateNumero(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->max('id') ?? 0;
        return sprintf('OM-%d-%04d', $year, $last + 1);
    }
}
