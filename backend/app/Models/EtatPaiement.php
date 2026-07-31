<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * État de paiement d'une mission : le document que le circuit valide.
 *
 * Un état = 1 mission × 1 TRANCHE (AVANCE ou RELIQUAT), N bénéficiaires.
 * Chaque état suit le circuit complet (contrôle → DAF → DG) et sa validation
 * finale ouvre la génération des fichiers de paiement DE SA TRANCHE. L'état
 * des reliquats ne peut être établi qu'une fois l'état des avances validé.
 */
class EtatPaiement extends Model
{
    use HasFactory;

    public const TRANCHE_AVANCE = 'AVANCE';

    public const TRANCHE_RELIQUAT = 'RELIQUAT';

    public const TRANCHES = [self::TRANCHE_AVANCE, self::TRANCHE_RELIQUAT];

    protected $table = 'etats_paiement';

    protected $fillable = [
        'mission_id',
        'tranche',              // AVANCE | RELIQUAT
        'numero',               // saisi manuellement (registre papier de l'agence)
        'statut',               // BROUILLON | CONTROLE | VISA_DAF | VALIDE | REJETE
        'nb_beneficiaires',
        'montant_total',
        'montant_avance',
        'montant_reliquat',
        'lignes',               // instantané du tableau, figé au récapitulatif
        'controle_par', 'controle_le', 'controle_nom', 'controle_fonction', 'signature_controle_hash',
        'signature_controle_image',
        'visa_daf_par', 'visa_daf_le', 'visa_daf_nom', 'visa_daf_fonction', 'signature_daf_hash',
        'signature_daf_image',
        'valide_dg_par', 'valide_dg_le', 'valide_dg_nom', 'valide_dg_fonction', 'signature_dg_hash',
        'signature_dg_image',
        'commentaire_rejet',
    ];

    protected function casts(): array
    {
        return [
            'nb_beneficiaires' => 'integer',
            'montant_total' => 'integer',
            'montant_avance' => 'integer',
            'montant_reliquat' => 'integer',
            'lignes' => 'array',
            'controle_le' => 'datetime',
            'visa_daf_le' => 'datetime',
            'valide_dg_le' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function controlePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controle_par');
    }

    public function visaDaf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visa_daf_par');
    }

    public function valideDg(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_dg_par');
    }

    /** L'état est-il définitivement validé (et donc payable) ? */
    public function estValide(): bool
    {
        return $this->statut === 'VALIDE';
    }

    /** L'état porte-t-il sur la tranche des avances (70 %) ? */
    public function estAvance(): bool
    {
        return $this->tranche === self::TRANCHE_AVANCE;
    }

    /** Libellé imprimable de la tranche (« des avances » / « des reliquats »). */
    public function libelleTranche(): string
    {
        return $this->estAvance() ? 'des avances' : 'des reliquats';
    }

    /** Le récapitulatif est-il encore modifiable (aucune signature apposée) ? */
    public function estModifiable(): bool
    {
        return $this->statut === 'BROUILLON';
    }
}
