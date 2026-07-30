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

    /*
     * ── Circuit de l'ÉTAT DE PAIEMENT ──
     *
     * C'est sur l'état, et non OM par OM, que se joue le circuit réel. Chaque
     * main qui se lève appelle la suivante : sans ces avis, un état contrôlé
     * pouvait attendre indéfiniment que le DAF pense à ouvrir sa file.
     */

    /** Un état attend l'intervention d'un poste du circuit. */
    public function etatAttendSignature(EtatPaiement $etat, string $role): void
    {
        $this->notifierRole($role, 'etat_attente', [
            'numero' => $etat->numero ?? 'non attribué',
            'mission' => $etat->mission?->objet ?? '—',
            'statut' => $etat->statut,
            'nb_beneficiaires' => $etat->nb_beneficiaires,
            'montant' => number_format($etat->montant_total, 0, ',', ' '),
        ]);
    }

    /** État rejeté : le gestionnaire OM doit reprendre le récapitulatif. */
    public function etatRejete(EtatPaiement $etat, string $motif): void
    {
        $this->notifierRole(User::ROLE_GESTIONNAIRE_OM, 'etat_rejete', [
            'numero' => $etat->numero ?? 'non attribué',
            'mission' => $etat->mission?->objet ?? '—',
            'motif' => $motif,
        ]);
    }

    /** État validé par le DG : l'agent financier peut générer les fichiers. */
    public function etatValide(EtatPaiement $etat): void
    {
        foreach ([User::ROLE_AGENT_FINANCIER, User::ROLE_GESTIONNAIRE_OM] as $role) {
            $this->notifierRole($role, 'etat_valide', [
                'numero' => $etat->numero ?? 'non attribué',
                'mission' => $etat->mission?->objet ?? '—',
                'nb_beneficiaires' => $etat->nb_beneficiaires,
                'montant' => number_format($etat->montant_total, 0, ',', ' '),
            ]);
        }
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
