<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** États de paiement de la mission (un par tranche : avances, reliquats). */
    public function etatsPaiement(): HasMany
    {
        return $this->hasMany(EtatPaiement::class);
    }

    /**
     * État des AVANCES : le premier document du circuit, celui dont la
     * validation rend les OM payables. Conservé en hasOne pour les écrans
     * qui n'affichent que l'avancement principal.
     */
    public function etatPaiement(): HasOne
    {
        return $this->hasOne(EtatPaiement::class)->where('tranche', EtatPaiement::TRANCHE_AVANCE);
    }

    /** État d'une tranche donnée (null tant que le récapitulatif n'est pas établi). */
    public function etatPourTranche(string $tranche): ?EtatPaiement
    {
        return $this->etatsPaiement()->where('tranche', $tranche)->first();
    }

    /**
     * Part d'avance RÉELLE de la mission, en % : celle de ses OM, figée à la
     * retranscription, et non le réglage courant (Parametres::partAvance), qui
     * a pu changer depuis. Les écrans qui décrivent une mission existante (état
     * de paiement, aperçu d'un lot) l'affichent, comme le PDF de l'OM.
     *
     * Même règle que PdfGenerator::libellesRepartition : un OM amendé dont
     * l'avance est engagée n'a plus de pourcentage (le reliquat a absorbé
     * l'écart), il est écarté ; les OM rejetés ou annulés aussi.
     *
     * @return int|null null sans montant à répartir (mission exonérée, sans OM)
     */
    public function partAvanceReelle(): ?int
    {
        $ordres = $this->ordresMission()
            ->whereNotIn('statut', ['REJETE', 'ANNULE'])
            ->where('montant_total', '>', 0)
            ->where(fn ($q) => $q->whereNull('avance_engagee')->orWhereDoesntHave('amendements'))
            ->get(['avance', 'montant_total']);

        $total = (int) $ordres->sum('montant_total');

        return $total > 0 ? (int) round($ordres->sum('avance') * 100 / $total) : null;
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ZoneGeographique::class, 'zone_code', 'code');
    }
}
