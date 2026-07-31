<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\EtatPaiement;
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
     * Un état de paiement attend la signature d'un rôle du circuit :
     * le récapitulatif appelle le contrôleur, le contrôle appelle le DAF,
     * le visa appelle le DG. Chacun est prévenu à son tour, jamais avant.
     */
    public function etatAttendSignature(EtatPaiement $etat, string $role): void
    {
        $this->notifierRole($role, 'etat_attend_signature', [
            'mission' => $etat->mission?->objet ?? '—',
            'destination' => $etat->mission?->destination ?? '—',
            'numero' => $etat->numero ?? '—',
            'tranche' => $etat->libelleTranche(),
            'montant' => number_format((int) $etat->montant_total, 0, ',', ' '),
        ]);
    }

    /** État validé par le DG : l'agent financier peut générer les fichiers de paiement. */
    public function etatValide(EtatPaiement $etat): void
    {
        $this->notifierRole(User::ROLE_AGENT_FINANCIER, 'etat_valide', [
            'mission' => $etat->mission?->objet ?? '—',
            'numero' => $etat->numero ?? '—',
            'tranche' => $etat->libelleTranche(),
            'montant' => number_format((int) $etat->montant_total, 0, ',', ' '),
        ]);
    }

    /** État rejeté : le gestionnaire OM est informé, avec le motif, pour correction. */
    public function etatRejete(EtatPaiement $etat, string $motif): void
    {
        $this->notifierRole(User::ROLE_GESTIONNAIRE_OM, 'etat_rejete', [
            'mission' => $etat->mission?->objet ?? '—',
            'numero' => $etat->numero ?? '—',
            'tranche' => $etat->libelleTranche(),
            'motif' => $motif,
        ]);
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
