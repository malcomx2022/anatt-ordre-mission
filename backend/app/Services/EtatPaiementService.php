<?php

namespace App\Services;

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Circuit de validation de l'état de paiement d'une mission.
 *
 *   récapitulatif ──▶ BROUILLON ──contrôle──▶ CONTROLE ──DAF──▶ VISA_DAF
 *                 ──DG (final)──▶ VALIDE
 *
 * Chaque étape est signée une seule fois, au niveau de l'état, et propagée à tous
 * les OM de la mission : c'est le geste réel du circuit papier (un état, deux
 * signatures) et cela évite de faire signer N ordres un par un.
 *
 * La propagation maintient le périmètre payable inchangé : les versements ne
 * deviennent éligibles que lorsque leurs OM passent à VALIDE, donc uniquement
 * après la validation finale du DG.
 */
class EtatPaiementService
{
    /** Statut d'OM correspondant à chaque étape de l'état. */
    private const PROPAGATION = [
        'CONTROLE' => Workflow::STATUT_VERIFIE,
        'VISA_DAF' => Workflow::STATUT_VISA_DAF,
        'VALIDE' => Workflow::STATUT_VALIDE,
        'REJETE' => 'REJETE',
    ];

    /**
     * Récapitulatif de la mission : crée ou rafraîchit l'état en BROUILLON.
     *
     * Recalcule l'instantané financier depuis les OM courants. Refusé dès qu'une
     * signature est apposée : on ne réécrit pas un document déjà signé.
     */
    public function recapituler(Mission $mission, User $user): EtatPaiement
    {
        return DB::transaction(function () use ($mission, $user) {
            $etat = EtatPaiement::where('mission_id', $mission->id)->lockForUpdate()->first();

            if ($etat && ! $etat->estModifiable()) {
                throw ValidationException::withMessages([
                    'etat' => ["Cet état est au statut « {$etat->statut} » : le récapitulatif n'est plus modifiable."],
                ]);
            }

            $ordres = OrdreMission::where('mission_id', $mission->id)
                ->whereNotIn('statut', ['REJETE', 'ANNULE'])
                ->get();

            if ($ordres->isEmpty()) {
                throw ValidationException::withMessages([
                    'etat' => ['Cette mission ne comporte aucun ordre de mission à récapituler.'],
                ]);
            }

            $etat = EtatPaiement::updateOrCreate(
                ['mission_id' => $mission->id],
                [
                    'statut' => 'BROUILLON',
                    'nb_beneficiaires' => $ordres->count(),
                    'montant_total' => (int) $ordres->sum('montant_total'),
                    'montant_avance' => (int) $ordres->sum('avance'),
                    'montant_reliquat' => (int) $ordres->sum('reliquat'),
                    'commentaire_rejet' => null,
                ],
            );

            AuditService::log($user, 'RECAP_ETAT', 'etat_paiement', $etat->id, [
                'mission' => $mission->objet,
                'nb_beneficiaires' => $etat->nb_beneficiaires,
                'montant_total' => $etat->montant_total,
            ]);

            return $etat->fresh();
        });
    }

    /** Numéro de l'état, saisi manuellement. Figé dès la validation finale. */
    public function definirNumero(EtatPaiement $etat, string $numero, User $user): EtatPaiement
    {
        if ($etat->estValide()) {
            throw ValidationException::withMessages([
                'numero' => ['Cet état est définitivement validé : son numéro ne peut plus être modifié.'],
            ]);
        }

        $existe = EtatPaiement::where('numero', $numero)->where('id', '!=', $etat->id)->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'numero' => ["Le numéro « {$numero} » est déjà attribué à un autre état."],
            ]);
        }

        $etat->update(['numero' => $numero]);

        AuditService::log($user, 'NUMERO_ETAT', 'etat_paiement', $etat->id, ['numero' => $numero]);

        return $etat->fresh();
    }

    /** Étape 1 — contrôle de conformité : BROUILLON → CONTROLE. */
    public function controler(EtatPaiement $etat, User $user): EtatPaiement
    {
        return $this->transition($etat, ['BROUILLON'], 'CONTROLE', $user, Workflow::ETAPE_VERIFICATION, 'CONTROL_ETAT');
    }

    /** Étape 2 — « Vu » du DAF : CONTROLE → VISA_DAF. Pas encore payable. */
    public function viser(EtatPaiement $etat, User $user): EtatPaiement
    {
        return $this->transition($etat, ['CONTROLE'], 'VISA_DAF', $user, Workflow::ETAPE_VISA_DAF, 'VISA_DAF_ETAT');
    }

    /**
     * Étape 3 — validation finale du Directeur Général : VISA_DAF → VALIDE.
     *
     * Verrouille l'état et rend les OM payables. Exige un numéro : un état de
     * paiement définitif ne circule pas sans référence au registre.
     */
    public function approuver(EtatPaiement $etat, User $user): EtatPaiement
    {
        if (Workflow::separationDesTaches() && (int) $etat->visa_daf_par === (int) $user->id) {
            throw ValidationException::withMessages([
                'signataire' => ['Vous avez apposé le visa DAF sur cet état : la validation finale revient à une autre personne.'],
            ]);
        }

        if (empty($etat->numero)) {
            throw ValidationException::withMessages([
                'numero' => ["Renseignez le numéro de l'état avant la validation finale."],
            ]);
        }

        return $this->transition($etat, ['VISA_DAF'], 'VALIDE', $user, Workflow::ETAPE_VALIDATION_DG, 'APPROVE_DG_ETAT');
    }

    /** Rejet motivé, possible à chaque étape. Efface les signatures amont. */
    public function rejeter(EtatPaiement $etat, string $motif, User $user): EtatPaiement
    {
        return $this->transition(
            $etat,
            ['BROUILLON', 'CONTROLE', 'VISA_DAF'],
            'REJETE',
            $user,
            null,
            'REJECT_ETAT',
            [
                'commentaire_rejet' => $motif,
                'controle_par' => null, 'controle_le' => null, 'controle_nom' => null,
                'controle_fonction' => null, 'signature_controle_hash' => null,
                'visa_daf_par' => null, 'visa_daf_le' => null, 'visa_daf_nom' => null,
                'visa_daf_fonction' => null, 'signature_daf_hash' => null,
            ],
        );
    }

    /**
     * Transition atomique de l'état, signature apposée, puis propagation à tous
     * les OM de la mission.
     *
     * @param  array<int,string>  $statutsAttendus
     * @param  array<string,mixed>  $champsSupplementaires
     */
    private function transition(
        EtatPaiement $etat,
        array $statutsAttendus,
        string $nouveauStatut,
        User $user,
        ?string $etape,
        string $action,
        array $champsSupplementaires = [],
    ): EtatPaiement {
        return DB::transaction(function () use ($etat, $statutsAttendus, $nouveauStatut, $user, $etape, $action, $champsSupplementaires) {
            $etat = EtatPaiement::lockForUpdate()->findOrFail($etat->id);

            if (! in_array($etat->statut, $statutsAttendus, true)) {
                throw ValidationException::withMessages([
                    'statut' => ["Cet état (statut « {$etat->statut} ») ne peut pas passer à « {$nouveauStatut} »."],
                ]);
            }

            $champs = array_merge(['statut' => $nouveauStatut], $champsSupplementaires);

            if ($etape !== null) {
                $champs = array_merge($champs, $this->empreinte($etat, $user, $etape));
            }

            $etat->update($champs);

            $this->propager($etat, $nouveauStatut, $user);

            AuditService::log($user, $action, 'etat_paiement', $etat->id, [
                'numero' => $etat->numero,
                'statut' => $nouveauStatut,
                'nb_beneficiaires' => $etat->nb_beneficiaires,
                'montant_total' => $etat->montant_total,
            ]);

            return $etat->fresh(['mission']);
        });
    }

    /**
     * Aligne les OM de la mission sur l'étape franchie par l'état.
     *
     * C'est ce qui fait qu'une signature unique sur l'état vaut pour ses N
     * bénéficiaires, sans jamais toucher au périmètre payable : un OM ne devient
     * VALIDE que lorsque l'état l'est.
     */
    private function propager(EtatPaiement $etat, string $statutEtat, User $user): void
    {
        $statutOm = self::PROPAGATION[$statutEtat] ?? null;

        if ($statutOm === null) {
            return;
        }

        $champs = ['statut' => $statutOm];

        // Un OM soldé ou annulé n'est jamais ramené en arrière.
        OrdreMission::where('mission_id', $etat->mission_id)
            ->whereNotIn('statut', ['SOLDE', 'ANNULE'])
            ->update($champs);
    }

    /**
     * Champs de signature : instantanés du signataire + scellé d'intégrité.
     *
     * Le nom et la fonction imprimés viennent en priorité des Paramètres (les
     * titulaires en poste, avec leur signature manuscrite scannée) et retombent
     * sur le compte du signataire si les paramètres ne sont pas renseignés.
     *
     * @return array<string,mixed>
     */
    private function empreinte(EtatPaiement $etat, User $user, string $etape): array
    {
        $maintenant = now();

        [$champ, $champHash, $posteParametre] = match ($etape) {
            Workflow::ETAPE_VERIFICATION => ['controle', 'signature_controle_hash', 'controleur'],
            Workflow::ETAPE_VISA_DAF => ['visa_daf', 'signature_daf_hash', 'daf'],
            Workflow::ETAPE_VALIDATION_DG => ['valide_dg', 'signature_dg_hash', 'dg'],
        };

        $signataire = SignatureParametres::pour($posteParametre);

        return [
            "{$champ}_par" => $user->id,
            "{$champ}_le" => $maintenant,
            // « Prénoms NOM », et non le seul patronyme : les prénoms sont saisis
            // dans les paramètres justement pour figurer sous la signature.
            "{$champ}_nom" => $signataire['nom_complet'] ?: $user->name,
            "{$champ}_fonction" => $signataire['fonction'] ?: ($user->fonction ?: Permissions::LIBELLES[$user->role] ?? null),
            $champHash => $this->sceller($etat, $user, $etape, $maintenant->toISOString()),
        ];
    }

    /**
     * Scellé d'intégrité de l'état : HMAC sur les montants signés.
     * Une altération postérieure des totaux fait diverger le scellé recalculé.
     */
    private function sceller(EtatPaiement $etat, User $user, string $etape, string $horodatage): string
    {
        $charge = implode('|', [
            $etape,
            (int) $etat->mission_id,
            (string) $etat->numero,
            (int) $etat->nb_beneficiaires,
            (int) $etat->montant_total,
            (int) $etat->montant_avance,
            (int) $etat->montant_reliquat,
            (int) $user->id,
            $horodatage,
        ]);

        return hash_hmac('sha256', $charge, (string) config('app.key'));
    }
}
