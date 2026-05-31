<?php

namespace App\Services;

use App\Models\Bareme;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Calcul de l'indemnité de mission (per diem) selon le barème officiel ANaTT.
 *
 *   per_diem = taux_journalier(catégorie × zone × type) × nb_jours
 *   avance   = round(per_diem / 3)        (≈ 33,33 %)
 *   reliquat = per_diem - avance          (≈ 66,67 %)
 *
 * Exonération : si la prise en charge est assurée par un organisme tiers,
 * aucun frais n'est alloué (montants à 0, aucun versement généré).
 */
class CalculIndemniteService
{
    /**
     * @return array{nb_jours:int, taux_journalier:int, montant_total:int, avance:int, reliquat:int, exonere:bool}
     */
    public function calculer(
        string $categorie,
        string $zoneCode,
        string $typePriseEnCharge,
        int $nbJours,
        bool $exonere = false,
    ): array {
        if ($exonere) {
            return [
                'nb_jours' => $nbJours,
                'taux_journalier' => 0,
                'montant_total' => 0,
                'avance' => 0,
                'reliquat' => 0,
                'exonere' => true,
            ];
        }

        $taux = Bareme::taux($categorie, $zoneCode, $typePriseEnCharge);

        if ($taux === null) {
            throw ValidationException::withMessages([
                'bareme' => ["Aucun barème pour {$categorie} / {$zoneCode} / {$typePriseEnCharge}."],
            ]);
        }

        $montantTotal = $taux * $nbJours;
        $avance = (int) round($montantTotal / 3);
        $reliquat = $montantTotal - $avance;

        return [
            'nb_jours' => $nbJours,
            'taux_journalier' => $taux,
            'montant_total' => $montantTotal,
            'avance' => $avance,
            'reliquat' => $reliquat,
            'exonere' => false,
        ];
    }

    /**
     * Nombre de jours indemnisables (règle des 12 h).
     *
     * Le schéma ne stocke que des dates (sans heures) : on retient donc le
     * décompte de jours inclusif (jour de départ et jour de retour comptés).
     * Si des heures de départ/retour sont fournies, la règle des 12 h s'applique
     * (toute fraction ≥ 12 h compte pour un jour entier).
     */
    public function nbJours(string|Carbon $depart, string|Carbon $retour): int
    {
        $depart = $depart instanceof Carbon ? $depart->copy() : Carbon::parse($depart);
        $retour = $retour instanceof Carbon ? $retour->copy() : Carbon::parse($retour);

        // Cas « dates seules » (minuit-minuit) : décompte inclusif.
        if ($depart->isStartOfDay() && $retour->isStartOfDay()) {
            return (int) abs($depart->diffInDays($retour)) + 1;
        }

        // Cas avec heures : règle des 12 h.
        $heures = abs($depart->diffInHours($retour));
        $joursPleins = intdiv((int) $heures, 24);
        $reste = $heures - ($joursPleins * 24);

        return max(1, $joursPleins + ($reste >= 12 ? 1 : 0));
    }
}
