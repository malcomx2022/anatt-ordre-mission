<?php

namespace App\Models;

use App\Observers\OrdreMissionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un ordre de mission = 1 enregistrement = 1 bénéficiaire (mono-bénéficiaire),
 * retranscrit depuis SEMO via sa reference_semo (clé naturelle unique).
 *
 * L'observer `OrdreMissionObserver` maintient l'état de paiement de la mission
 * synchronisé avec ces OM : dès qu'un OM change de montant ou est rejeté, le
 * brouillon de l'état est recalé, sans intervention manuelle.
 */
#[ObservedBy(OrdreMissionObserver::class)]
class OrdreMission extends Model
{
    use HasFactory;

    protected $table = 'ordre_missions';

    protected $fillable = [
        'reference_semo',
        'mission_id',
        'agent_id',
        'role',                 // PARTICIPANT | CAV
        'categorie',            // INDICE_SUP_800 | INDICE_400_799 | INDICE_INF_400 (reprise SEMO)
        'zone_code',
        'type_prise_en_charge',
        'nb_jours',
        'lignes_per_diem',      // répartition jours par type [{type, taux, nb_jours, montant}]
        'montant_transport',
        'montant_total',
        'avance',
        'reliquat',
        'avance_engagee',
        'statut',               // IMPORTE|VALIDE|REJETE|SOLDE|ANNULE (VERIFIE/VISA_DAF : valeurs héritées, plus produites — circuit OM en une étape)
        'conducteur_a_bord',    // mention logistique uniquement
        'reference_autorisation',
        'moyen_transport',
        'commentaire_rejet',
        // ── Signatures du circuit de validation (instantanés + scellé d'intégrité) ──
        'verifie_par', 'verifie_le', 'verifie_nom', 'verifie_fonction',
        'verifie_commentaire', 'signature_verif_hash',
        'visa_daf_par', 'visa_daf_le', 'visa_daf_nom', 'visa_daf_fonction',
        'visa_daf_commentaire', 'signature_daf_hash',
        'valide_dg_par', 'valide_dg_le', 'valide_dg_nom', 'valide_dg_fonction',
        'signature_dg_hash',
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
            'avance_engagee' => 'integer',
            'verifie_le' => 'datetime',
            'visa_daf_le' => 'datetime',
            'valide_dg_le' => 'datetime',
        ];
    }

    /** Vérificateur (étape 1 : contrôle de conformité). */
    public function verifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    /** Signataire du visa DAF (étape 2). */
    public function visaDaf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visa_daf_par');
    }

    /** Signataire de la validation finale du DG (étape 3 : verrouillage). */
    public function valideDg(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_dg_par');
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
     * Passe l'OM à SOLDE si tous ses versements sont réglés : payés (CONFIRME)
     * ou, pour un trop-perçu, récupérés par retenue (RECOUVRE). Un versement nul
     * (tranche vide : part d'avance à 0 ou 100 %, reliquat ramené à 0 par un
     * amendement) n'entre dans aucun fichier : il ne retient pas l'OM. Un
     * trop-perçu A_RECOUVRER le retient. Appelée à la confirmation d'un lot, au
     * constat d'un recouvrement et après un amendement (cf. docs/circuits-validation.md §3).
     */
    public static function solderSiRegle(int $omId): void
    {
        $resteARegler = Versement::where('ordre_mission_id', $omId)
            ->whereNotIn('statut', ['CONFIRME', 'RECOUVRE'])
            ->where('montant', '!=', 0)
            ->exists();

        if (! $resteARegler) {
            static::where('id', $omId)
                ->whereNotIn('statut', ['SOLDE', 'ANNULE'])
                ->update(['statut' => 'SOLDE']);
        }
    }

    /** Journal des amendements (rallonge / réduction des jours facturés). */
    public function amendements(): HasMany
    {
        return $this->hasMany(OmAmendement::class);
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
