<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\TauxPerDiem;
use Carbon\Carbon;

class PerDiemCalculator
{
    public function calculer(
        Agent $agent,
        string $dateDepart,
        string $dateRetour,
        string $destinationType = 'LOCALE',
    ): array {
        $nbJours = Carbon::parse($dateRetour)->diffInDays(Carbon::parse($dateDepart)) + 1;

        $taux = TauxPerDiem::where('categorie', $agent->categorie)
            ->where('destination_type', $destinationType)
            ->where('annee', now()->year)
            ->where('actif', true)
            ->first();

        if (! $taux) {
            return [
                'nb_jours' => $nbJours,
                'taux' => null,
                'montant' => 0,
                'erreur' => 'Aucun taux actif trouve pour cette categorie et destination.',
            ];
        }

        return [
            'nb_jours' => $nbJours,
            'taux' => $taux,
            'montant' => $nbJours * $taux->montant_journalier,
            'erreur' => null,
        ];
    }
}
