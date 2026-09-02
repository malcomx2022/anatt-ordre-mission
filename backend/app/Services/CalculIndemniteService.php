<?php

namespace App\Services;

use App\Models\Bareme;
use App\Support\Parametres;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Calcul de l'indemnité de mission (per diem) selon le barème officiel ANaTT.
 *
 *   per_diem = taux_journalier(catégorie × zone × type) × nb_jours
 *   avance   = round(per_diem × partAvance/100)   (paramétrable, 70 % par défaut)
 *   reliquat = per_diem - avance
 *
 * Le pourcentage d'avance se configure dans les Paramètres (SUPER_ADMIN).
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
        [$avance, $reliquat] = $this->repartir($montantTotal);

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
     * Calcul détaillé « état ANaTT » : un bénéficiaire cumule plusieurs types de
     * prise en charge (ex. 1 jour UN_REPAS + 6 jours JOURNEE_COMPLETE) + un
     * éventuel montant de transport.
     *
     *   per_diem       = Σ (taux(type) × nb_jours)   sur chaque ligne
     *   montant_total  = per_diem + montant_transport
     *   avance         = round(montant_total × partAvance/100)  (paramétrable, 70 % par défaut)
     *   reliquat       = montant_total - avance
     *
     * @param  array<int,array{type:string,nb_jours:int}>  $lignes
     * @return array{lignes:array<int,array{type:string,taux:int,nb_jours:int,montant:int}>, type_principal:?string, nb_jours:int, per_diem:int, montant_transport:int, montant_total:int, avance:int, reliquat:int, exonere:bool}
     */
    public function calculerDetaille(
        string $categorie,
        string $zoneCode,
        array $lignes,
        bool $exonere = false,
        int $montantTransport = 0,
    ): array {
        // Ne conserver que les lignes avec au moins un jour.
        $lignes = array_values(array_filter(
            $lignes,
            fn ($l) => (int) ($l['nb_jours'] ?? 0) > 0,
        ));

        if ($exonere || empty($lignes)) {
            return [
                'lignes' => [],
                'type_principal' => $lignes[0]['type'] ?? null,
                'nb_jours' => array_sum(array_map(fn ($l) => (int) $l['nb_jours'], $lignes)),
                'per_diem' => 0,
                'montant_transport' => 0,
                'montant_total' => 0,
                'avance' => 0,
                'reliquat' => 0,
                'exonere' => true,
            ];
        }

        $detail = [];
        $perDiem = 0;
        $nbJoursTotal = 0;
        $typePrincipal = null;
        $joursPrincipal = -1;

        foreach ($lignes as $ligne) {
            $type = $ligne['type'];
            $nbJours = (int) $ligne['nb_jours'];
            $taux = Bareme::taux($categorie, $zoneCode, $type);

            if ($taux === null) {
                throw ValidationException::withMessages([
                    'bareme' => ["Aucun barème pour {$categorie} / {$zoneCode} / {$type}."],
                ]);
            }

            $montant = $taux * $nbJours;
            $detail[] = ['type' => $type, 'taux' => $taux, 'nb_jours' => $nbJours, 'montant' => $montant];
            $perDiem += $montant;
            $nbJoursTotal += $nbJours;

            // Type dominant = celui totalisant le plus de jours (pour l'affichage/compat).
            if ($nbJours > $joursPrincipal) {
                $joursPrincipal = $nbJours;
                $typePrincipal = $type;
            }
        }

        $montantTotal = $perDiem + max(0, $montantTransport);
        [$avance, $reliquat] = $this->repartir($montantTotal);

        return [
            'lignes' => $detail,
            'type_principal' => $typePrincipal,
            'nb_jours' => $nbJoursTotal,
            'per_diem' => $perDiem,
            'montant_transport' => max(0, $montantTransport),
            'montant_total' => $montantTotal,
            'avance' => $avance,
            'reliquat' => $reliquat,
            'exonere' => false,
        ];
    }

    /**
     * Répartition AVANCE / RELIQUAT selon le pourcentage paramétrable.
     *
     * @return array{0:int,1:int} [avance, reliquat]
     */
    /**
     * Répartition avance / reliquat d'un montant total.
     *
     * - $avanceEngagee === null (cas courant) : l'avance est calculée au taux
     *   paramétrable (partAvance, 70 % par défaut) et le reliquat prend le reste.
     * - $avanceEngagee fourni (avance déjà figée, ex. après validation des
     *   avances) : l'avance NE bouge plus ; le reliquat absorbe tout l'écart et
     *   peut devenir NÉGATIF (trop-perçu à récupérer). Cf. docs/amendement-om.md.
     *
     * @return array{0:int,1:int} [avance, reliquat]
     */
    public function repartir(int $montantTotal, ?int $avanceEngagee = null): array
    {
        if ($avanceEngagee !== null) {
            return [$avanceEngagee, $montantTotal - $avanceEngagee];
        }

        $taux = Parametres::partAvance();
        $avance = (int) round($montantTotal * $taux / 100);

        return [$avance, $montantTotal - $avance];
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
