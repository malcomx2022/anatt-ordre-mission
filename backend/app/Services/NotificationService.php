<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\OrdreMission;
use App\Models\Paiement;
use App\Models\User;

class NotificationService
{
    public function missionSoumise(OrdreMission $ordre): void
    {
        $chefs = User::where('role', 'CHEF_SERVICE')->get();

        foreach ($chefs as $chef) {
            if ($chef->agent && $chef->agent->service === $ordre->agent->service) {
                SendNotification::dispatch($chef->id, 'mission_soumise', [
                    'numero' => $ordre->numero,
                    'agent' => $ordre->agent->nom_complet,
                    'destination' => $ordre->destination,
                ]);
            }
        }
    }

    public function missionValideeN1(OrdreMission $ordre): void
    {
        // Notifier le createur
        if ($ordre->agent->user_id) {
            SendNotification::dispatch($ordre->agent->user_id, 'mission_validee', [
                'numero' => $ordre->numero,
                'validateur' => $ordre->valideurChef?->name ?? 'Chef de service',
            ]);
        }

        // Notifier les directeurs pour approbation
        $directeurs = User::where('role', 'DIRECTEUR')->get();
        foreach ($directeurs as $dg) {
            SendNotification::dispatch($dg->id, 'mission_soumise', [
                'numero' => $ordre->numero,
                'agent' => $ordre->agent->nom_complet,
                'destination' => $ordre->destination,
            ]);
        }
    }

    public function missionApprouvee(OrdreMission $ordre): void
    {
        // Notifier le createur
        if ($ordre->agent->user_id) {
            SendNotification::dispatch($ordre->agent->user_id, 'mission_approuvee', [
                'numero' => $ordre->numero,
                'montant' => number_format($ordre->montant_per_diem, 0, ',', ' '),
            ]);
        }

        // Notifier le DRH pour lancer le paiement
        $drhs = User::where('role', 'DRH')->get();
        foreach ($drhs as $drh) {
            SendNotification::dispatch($drh->id, 'mission_approuvee', [
                'numero' => $ordre->numero,
                'montant' => number_format($ordre->montant_per_diem, 0, ',', ' '),
            ]);
        }
    }

    public function missionRejetee(OrdreMission $ordre): void
    {
        if ($ordre->agent->user_id) {
            SendNotification::dispatch($ordre->agent->user_id, 'mission_rejetee', [
                'numero' => $ordre->numero,
                'commentaire' => $ordre->commentaire_rejet,
            ]);
        }
    }

    public function paiementEffectue(Paiement $paiement): void
    {
        $ordre = $paiement->ordreMission;
        $agent = $paiement->agent;

        if ($agent->user_id) {
            SendNotification::dispatch($agent->user_id, 'paiement_effectue', [
                'numero' => $ordre->numero,
                'montant' => number_format($paiement->montant, 0, ',', ' '),
                'operateur' => $paiement->operateur,
            ]);
        }
    }

    public function paiementEchoue(Paiement $paiement): void
    {
        $ordre = $paiement->ordreMission;

        // Notifier les admins
        $admins = User::where('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            SendNotification::dispatch($admin->id, 'paiement_echec', [
                'numero' => $ordre->numero,
                'montant' => number_format($paiement->montant, 0, ',', ' '),
                'operateur' => $paiement->operateur,
            ]);
        }
    }
}
