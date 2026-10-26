<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\EtatPaiement;
use App\Models\LotPaiement;
use App\Models\Notification;
use App\Models\OrdreMission;
use App\Models\User;
use App\Support\NotificationContenu;

/**
 * Notifications e-mail des handoffs internes du workflow.
 *
 * Note : dans le nouveau modèle, les agents (bénéficiaires) ne sont PAS des comptes
 * utilisateurs et n'ont pas d'e-mail. Les notifications ciblent donc les utilisateurs
 * applicatifs selon la permission qui les rend responsables de l'étape suivante
 * (paiements.generer, om.retranscrire, om.valider / om.viser / om.approuver…).
 */
class NotificationService
{
    /** Signature attendue d'un rôle du circuit → permission qui la porte. */
    private const SIGNATURE_PAR_ROLE = [
        User::ROLE_CONTROLEUR => 'om.valider',
        User::ROLE_DAF => 'om.viser',
        User::ROLE_DG => 'om.approuver',
    ];

    /** OM validé : l'agent financier peut préparer le paiement. */
    public function omValide(OrdreMission $ordre): void
    {
        $this->notifier(['paiements.generer'], 'om_valide', [
            'reference_semo' => $ordre->reference_semo,
            'beneficiaire' => $ordre->agent?->nom_complet ?? '—',
            'montant' => number_format($ordre->montant_total, 0, ',', ' '),
        ]);
    }

    /** OM rejeté : le gestionnaire OM est informé. */
    public function omRejete(OrdreMission $ordre): void
    {
        $this->notifier(['om.retranscrire'], 'om_rejete', [
            'reference_semo' => $ordre->reference_semo,
            'motif' => $ordre->commentaire_rejet ?? '—',
        ]);
    }

    /** Lot de paiement confirmé : gestionnaire OM et administration sont informés. */
    public function lotConfirme(LotPaiement $lot): void
    {
        $this->notifier(['om.retranscrire', 'utilisateurs.gerer'], 'lot_confirme', [
            'mission' => $lot->mission?->destination ?? '—',
            'tranche' => $lot->tranche,
            'operateur' => $lot->operateur,
            'montant' => number_format($lot->montant_total, 0, ',', ' '),
        ]);
    }

    /**
     * Un état de paiement attend la signature d'un rôle du circuit :
     * le récapitulatif appelle le contrôleur, le contrôle appelle le DAF,
     * le visa appelle le DG. Chacun est prévenu à son tour, jamais avant.
     */
    public function etatAttendSignature(EtatPaiement $etat, string $role): void
    {
        $this->notifier([self::SIGNATURE_PAR_ROLE[$role]], 'etat_attend_signature', [
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
        $this->notifier(['paiements.generer'], 'etat_valide', [
            'mission' => $etat->mission?->objet ?? '—',
            'numero' => $etat->numero ?? '—',
            'tranche' => $etat->libelleTranche(),
            'montant' => number_format((int) $etat->montant_total, 0, ',', ' '),
        ]);
    }

    /**
     * Un amendement a produit un TROP-PERÇU (reliquat négatif) : l'agent financier
     * est prévenu qu'une régularisation (retenue sur salaire, hors Mobile Money)
     * l'attend sur l'écran Régularisations. Cf. docs/amendement-om.md.
     */
    public function tropPercuCree(OrdreMission $ordre, int $montantARecouvrer): void
    {
        $this->notifier(['paiements.confirmer'], 'trop_percu_cree', [
            'reference_semo' => $ordre->reference_semo,
            'beneficiaire' => $ordre->agent?->nom_complet ?? '—',
            'montant' => number_format($montantARecouvrer, 0, ',', ' '),
        ]);
    }

    /** État rejeté : le gestionnaire OM est informé, avec le motif, pour correction. */
    public function etatRejete(EtatPaiement $etat, string $motif): void
    {
        $this->notifier(['om.retranscrire'], 'etat_rejete', [
            'mission' => $etat->mission?->objet ?? '—',
            'numero' => $etat->numero ?? '—',
            'tranche' => $etat->libelleTranche(),
            'motif' => $motif,
        ]);
    }

    /**
     * Notifie les utilisateurs ACTIFS qui détiennent, dans leurs droits EFFECTIFS
     * (profil, rôle personnalisé, accords et retraits individuels), au moins une des
     * permissions de l'action : la notification suit l'autorisation (décision D1,
     * docs/rbac-flexible.md). Le SUPER_ADMIN, dont l'accès implicite n'est pas une
     * responsabilité du circuit, n'est pas visé ; un compte désactivé ne reçoit rien.
     * Chaque destinataire reçoit une seule notification in-app (feed de la cloche)
     * et un seul e-mail, qui partagent le même sujet/corps (NotificationContenu).
     *
     * @param  string[]  $permissions
     * @param  array<string,mixed>  $data
     */
    private function notifier(array $permissions, string $type, array $data): void
    {
        User::where('actif', true)
            ->where('role', '!=', User::ROLE_SUPER_ADMIN)
            ->with('permissionOverrides')
            ->get()
            ->filter(fn (User $u) => array_intersect($permissions, $u->permissions()) !== [])
            ->each(function (User $u) use ($type, $data) {
                Notification::create([
                    'user_id' => $u->id,
                    'type' => $type,
                    'titre' => NotificationContenu::sujet($type, $data),
                    'message' => NotificationContenu::corps($type, $data),
                    'data' => $data,
                ]);

                SendNotification::dispatch($u->id, $type, $data);
            });
    }
}
