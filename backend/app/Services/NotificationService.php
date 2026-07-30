<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\LotPaiement;
use App\Models\OrdreMission;
use App\Models\User;

/**
 * Notifications e-mail des handoffs internes du workflow.
 *
 * Note : dans le nouveau modèle, les agents (bénéficiaires) ne sont PAS des comptes
 * utilisateurs et n'ont pas d'e-mail. Les notifications ciblent donc les utilisateurs
 * applicatifs par rôle (gestionnaire_om / agent_financier / admin).
 */
class NotificationService
{
    /** OM validé : l'agent financier peut préparer le paiement. */
    public function omValide(OrdreMission $ordre): void
    {
        $this->notifierRole(User::ROLE_AGENT_FINANCIER, 'om_valide', [
            'reference_semo' => $ordre->reference_semo,
            'beneficiaire' => $ordre->agent?->nom_complet ?? '—',
            'montant' => number_format($ordre->montant_total, 0, ',', ' '),
        ]);
    }

    /** OM rejeté : le gestionnaire OM est informé. */
    public function omRejete(OrdreMission $ordre): void
    {
        $this->notifierRole(User::ROLE_GESTIONNAIRE_OM, 'om_rejete', [
            'reference_semo' => $ordre->reference_semo,
            'motif' => $ordre->commentaire_rejet ?? '—',
        ]);
    }

    /** Lot de paiement confirmé : gestionnaire OM et admin sont informés. */
    public function lotConfirme(LotPaiement $lot): void
    {
        foreach ([User::ROLE_GESTIONNAIRE_OM, User::ROLE_ADMIN] as $role) {
            $this->notifierRole($role, 'lot_confirme', [
                'mission' => $lot->mission?->destination ?? '—',
                'tranche' => $lot->tranche,
                'operateur' => $lot->operateur,
                'montant' => number_format($lot->montant_total, 0, ',', ' '),
            ]);
        }
    }

    /**
     * Dispatche une notification à tous les utilisateurs d'un rôle.
     *
     * @param  array<string,mixed>  $data
     */
    private function notifierRole(string $role, string $type, array $data): void
    {
        User::where('role', $role)->get()->each(
            fn (User $u) => SendNotification::dispatch($u->id, $type, $data),
        );
    }
}
