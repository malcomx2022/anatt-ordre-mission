<?php

namespace App\Services;

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Workflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Circuit de validation de l'état de paiement d'une mission.
 *
 *   récapitulatif ──▶ BROUILLON ──contrôle──▶ CONTROLE ──DAF──▶ VISA_DAF
 *                 ──DG (final)──▶ VALIDE
 *
 * Le circuit ne porte QUE sur l'état : c'est lui, le brouillon que l'on contrôle
 * puis fait signer. Les ordres de mission, eux, gardent le statut sous lequel ils
 * ont été retranscrits depuis SEMO (IMPORTE) — ils ne sont pas revalidés un par
 * un. Seule la validation finale du DG les fait passer à VALIDE, ce qui les rend
 * payables : c'est le seul et unique moment où l'état touche au statut des OM.
 *
 * Le périmètre payable est donc inchangé : un versement ne devient éligible
 * qu'après la validation finale, exactement comme avant.
 */
class EtatPaiementService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Statut d'OM correspondant à une étape de l'état.
     *
     * Seule la validation finale propage : les OM deviennent VALIDE (payables).
     * Les étapes intermédiaires (contrôle, visa DAF) et le rejet ne touchent PAS
     * les OM — ils restent à leur statut de retranscription SEMO.
     */
    private const PROPAGATION = [
        'VALIDE' => Workflow::STATUT_VALIDE,
    ];

    /**
     * Récapitulatif de la mission : crée ou rafraîchit l'état d'UNE TRANCHE
     * en BROUILLON (l'état des avances par défaut).
     *
     * Recalcule l'instantané financier de la tranche depuis les OM courants et
     * FIGE le tableau des bénéficiaires (colonne `lignes`) : le document signé
     * ne peut plus diverger de ce qui a été présenté au signataire. Refusé dès
     * qu'une signature est apposée (CONTROLE, VISA_DAF, VALIDE) : on ne réécrit
     * pas un document déjà signé. Un état REJETÉ, en revanche, se refait.
     *
     * SÉQUENCEMENT : l'état des reliquats ne peut être établi qu'une fois
     * l'état des avances VALIDÉ — le circuit des reliquats ne s'ouvre qu'après
     * la décision finale sur les avances.
     *
     * Un état à montants nuls est LICITE (mission exonérée — prise en charge
     * par un tiers) : le brouillon reste éditable et téléchargeable.
     */
    public function recapituler(Mission $mission, User $user, string $tranche = EtatPaiement::TRANCHE_AVANCE): EtatPaiement
    {
        $etat = DB::transaction(function () use ($mission, $user, $tranche) {
            if ($tranche === EtatPaiement::TRANCHE_RELIQUAT) {
                $avance = EtatPaiement::where('mission_id', $mission->id)
                    ->where('tranche', EtatPaiement::TRANCHE_AVANCE)
                    ->first();

                if (! $avance?->estValide()) {
                    throw ValidationException::withMessages([
                        'etat' => ["L'état des avances doit être validé par le circuit (contrôle, visa DAF, Directeur Général) avant d'établir l'état des reliquats."],
                    ]);
                }
            }

            $etat = EtatPaiement::where('mission_id', $mission->id)
                ->where('tranche', $tranche)
                ->lockForUpdate()
                ->first();

            if ($etat && ! $etat->estModifiable() && $etat->statut !== 'REJETE') {
                throw ValidationException::withMessages([
                    'etat' => ["Cet état {$this->libelle($tranche)} est au statut « {$etat->statut} » : le récapitulatif n'est plus modifiable."],
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
                ['mission_id' => $mission->id, 'tranche' => $tranche],
                array_merge([
                    'statut' => 'BROUILLON',
                    'nb_beneficiaires' => $ordres->count(),
                    // Le tableau tel qu'il sera imprimé : figé avec les totaux.
                    'lignes' => LignesEtatPaiement::pourMission($mission->id),
                    'commentaire_rejet' => null,
                ], $this->montantsTranche($ordres, $tranche)),
            );

            AuditService::log($user, 'RECAP_ETAT', 'etat_paiement', $etat->id, [
                'mission' => $mission->objet,
                'tranche' => $tranche,
                'nb_beneficiaires' => $etat->nb_beneficiaires,
                'montant_total' => $etat->montant_total,
            ]);

            return $etat->fresh();
        });

        // Le circuit commence : le contrôleur est avisé qu'un brouillon l'attend.
        $this->notifications->etatAttendSignature($etat->loadMissing('mission'), User::ROLE_CONTROLEUR);

        return $etat;
    }

    /**
     * Instantané financier d'une tranche : `montant_total` porte la somme de la
     * tranche (avances OU reliquats), la colonne de l'autre tranche est à zéro.
     *
     * Le total des reliquats est le montant PAYABLE : un reliquat négatif
     * (trop-perçu après amendement) ne part jamais dans un fichier de paiement
     * (cf. Versement::scopeEligiblesLot) — il est récupéré par retenue sur
     * salaire. Le compter ici ferait signer un montant différent de celui
     * décaissé ; il figure à part sur le document (« trop-perçus »).
     *
     * @param  Collection<int,OrdreMission>  $ordres
     * @return array{montant_total:int, montant_avance:int, montant_reliquat:int}
     */
    private function montantsTranche($ordres, string $tranche): array
    {
        $avance = (int) $ordres->sum('avance');
        $reliquat = (int) $ordres->sum(fn (OrdreMission $om) => max(0, (int) $om->reliquat));

        return $tranche === EtatPaiement::TRANCHE_AVANCE
            ? ['montant_total' => $avance, 'montant_avance' => $avance, 'montant_reliquat' => 0]
            : ['montant_total' => $reliquat, 'montant_avance' => 0, 'montant_reliquat' => $reliquat];
    }

    /** « des avances » / « des reliquats », pour les messages. */
    private function libelle(string $tranche): string
    {
        return $tranche === EtatPaiement::TRANCHE_AVANCE ? 'des avances' : 'des reliquats';
    }

    /**
     * Resynchronisation silencieuse de l'état avec les OM courants de la mission.
     *
     * Déclenchée par l'observer d'OrdreMission : dès qu'un OM est créé, modifié
     * ou supprimé, on recale les compteurs de l'état sans intervention manuelle
     * — le brouillon reste fidèle aux OM sans que l'on ait à cliquer sur
     * « Rafraîchir ». Contrairement à `recapituler()`, cette méthode :
     *   - est silencieuse (pas d'audit log : les mutations d'OM sont déjà
     *     tracées, on ne veut pas dédoubler l'historique),
     *   - est tolérante : aucun état → no-op, aucun OM vivant → montants remis
     *     à zéro (l'écran retombera sur l'empty-state),
     *   - refuse de toucher un document signé (CONTROLE / VISA_DAF / VALIDE) :
     *     les instantanés financiers sont scellés dès qu'une signature est
     *     apposée. On n'altère JAMAIS un document déjà validé.
     *
     * L'appel est protégé par un verrou pessimiste pour éviter qu'un
     * enchaînement rapide de saves d'OM ne fasse diverger deux processus
     * concurrents.
     */
    public function synchroniser(int $missionId): void
    {
        DB::transaction(function () use ($missionId) {
            $etats = EtatPaiement::where('mission_id', $missionId)->lockForUpdate()->get();

            // Seuls les états non signés se recalent : un document signé
            // (CONTROLE / VISA_DAF / VALIDE) n'est JAMAIS altéré par un OM.
            $modifiables = $etats->reject(
                fn (EtatPaiement $e) => in_array($e->statut, ['CONTROLE', 'VISA_DAF', 'VALIDE'], true),
            );

            if ($modifiables->isEmpty()) {
                return;
            }

            $ordres = OrdreMission::where('mission_id', $missionId)
                ->whereNotIn('statut', ['REJETE', 'ANNULE'])
                ->get();

            $lignes = LignesEtatPaiement::pourMission($missionId);

            // Chaque état se recale sur les montants de SA tranche ; le tableau
            // figé suit tant que l'état n'est pas signé.
            foreach ($modifiables as $etat) {
                $etat->update(array_merge([
                    'nb_beneficiaires' => $ordres->count(),
                    'lignes' => $lignes,
                ], $this->montantsTranche($ordres, $etat->tranche)));
            }
        });
    }

    /**
     * Numéro de l'état, saisi manuellement selon le registre de l'agence.
     *
     * Modifiable pendant tout le circuit (une erreur de saisie de registre doit
     * pouvoir être corrigée avant l'arrêté) ; figé par la validation finale,
     * qui est aussi celle qui l'exige.
     *
     * Le préfixe « N° » est retiré à l'entrée : il est posé par les écrans et
     * les PDF, le stocker le ferait apparaître deux fois.
     */
    public function definirNumero(EtatPaiement $etat, string $numero, User $user): EtatPaiement
    {
        if ($etat->estValide()) {
            throw ValidationException::withMessages([
                'numero' => ['Cet état est validé : son numéro est figé avec le document.'],
            ]);
        }

        $numero = EtatPaiement::normaliserNumero($numero);

        if ($numero === null) {
            throw ValidationException::withMessages([
                'numero' => ['Saisissez le numéro de l’état tel qu’il figure au registre.'],
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
        $etat = $this->transition($etat, ['BROUILLON'], 'CONTROLE', $user, Workflow::ETAPE_VERIFICATION, 'CONTROL_ETAT');

        // L'état contrôlé attend maintenant le « Vu » du DAF.
        $this->notifications->etatAttendSignature($etat, User::ROLE_DAF);

        return $etat;
    }

    /** Étape 2 — « Vu » du DAF : CONTROLE → VISA_DAF. Pas encore payable. */
    public function viser(EtatPaiement $etat, User $user): EtatPaiement
    {
        // Séparation des tâches : le DAF est distinct du contrôleur.
        if (Workflow::separationDesTaches() && (int) $etat->controle_par === (int) $user->id) {
            throw ValidationException::withMessages([
                'signataire' => ['Vous avez contrôlé cet état : le visa DAF revient à une autre personne.'],
            ]);
        }

        $etat = $this->transition($etat, ['CONTROLE'], 'VISA_DAF', $user, Workflow::ETAPE_VISA_DAF, 'VISA_DAF_ETAT');

        // Visé : la validation finale revient au Directeur Général.
        $this->notifications->etatAttendSignature($etat, User::ROLE_DG);

        return $etat;
    }

    /**
     * Étape 3 — validation finale du Directeur Général : VISA_DAF → VALIDE.
     *
     * Verrouille l'état et rend les OM payables. Exige un numéro : un état de
     * paiement définitif ne circule pas sans référence au registre.
     */
    public function approuver(EtatPaiement $etat, User $user): EtatPaiement
    {
        // Séparation des tâches : la validation finale revient à une personne
        // distincte des DEUX signataires amont (contrôleur ET DAF).
        if (Workflow::separationDesTaches()
            && in_array((int) $user->id, [(int) $etat->controle_par, (int) $etat->visa_daf_par], true)) {
            throw ValidationException::withMessages([
                'signataire' => ['Vous êtes déjà intervenu sur cet état (contrôle ou visa DAF) : la validation finale revient à une autre personne.'],
            ]);
        }

        if (empty($etat->numero)) {
            throw ValidationException::withMessages([
                'numero' => ["Renseignez le numéro de l'état avant la validation finale."],
            ]);
        }

        $etat = $this->transition($etat, ['VISA_DAF'], 'VALIDE', $user, Workflow::ETAPE_VALIDATION_DG, 'APPROVE_DG_ETAT');

        // Paiement ouvert : l'agent financier peut générer les fichiers.
        $this->notifications->etatValide($etat);

        return $etat;
    }

    /**
     * Rejet motivé, possible à chaque étape. Efface les signatures amont, y
     * compris les paraphes archivés : une signature manuscrite annulée ne se
     * conserve pas sur le disque.
     */
    public function rejeter(EtatPaiement $etat, string $motif, User $user): EtatPaiement
    {
        $archives = [
            $etat->signature_controle_image,
            $etat->signature_daf_image,
            $etat->signature_dg_image,
        ];

        $etat = $this->transition(
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
                'signature_controle_image' => null,
                'visa_daf_par' => null, 'visa_daf_le' => null, 'visa_daf_nom' => null,
                'visa_daf_fonction' => null, 'signature_daf_hash' => null,
                'signature_daf_image' => null,
                'signature_dg_image' => null,
            ],
        );

        // Purge après commit : les fichiers ne partent que si le rejet a abouti.
        SignatureParametres::supprimerArchives($etat, $archives);

        // Le gestionnaire est avisé, avec le motif, pour corriger et refaire.
        $this->notifications->etatRejete($etat, $motif);

        return $etat;
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
                'tranche' => $etat->tranche,
                'statut' => $nouveauStatut,
                'nb_beneficiaires' => $etat->nb_beneficiaires,
                'montant_total' => $etat->montant_total,
            ]);

            return $etat->fresh(['mission']);
        });
    }

    /**
     * Rend les OM de la mission payables lorsque — et seulement lorsque — l'état
     * est validé par le DG. Une signature unique sur l'état vaut alors pour ses N
     * bénéficiaires, sans les avoir fait transiter par des statuts intermédiaires.
     *
     * Les étapes contrôle / visa DAF, et le rejet, ne figurent pas dans
     * PROPAGATION : les OM y gardent leur statut de retranscription SEMO.
     */
    private function propager(EtatPaiement $etat, string $statutEtat, User $user): void
    {
        // Seule la validation de l'état des AVANCES fait passer les OM à VALIDE :
        // c'est le premier circuit bouclé sur la mission, celui qui rend les OM
        // payables. L'état des reliquats arrive après coup — les OM sont déjà
        // VALIDE, il n'y a rien à propager une seconde fois.
        if (! $etat->estAvance()) {
            return;
        }

        $statutOm = self::PROPAGATION[$statutEtat] ?? null;

        if ($statutOm === null) {
            return;
        }

        // Un OM soldé ou annulé n'est jamais ramené en arrière ; un OM rejeté est
        // absent du récapitulatif signé (cf. recapituler) : la validation de
        // l'état ne doit pas le rendre payable.
        OrdreMission::where('mission_id', $etat->mission_id)
            ->whereNotIn('statut', ['SOLDE', 'ANNULE', 'REJETE'])
            ->update(['statut' => $statutOm]);

        // Fige l'AVANCE ENGAGÉE : à partir d'ici l'avance est décaissable et ne
        // doit plus se recalculer. Un amendement ultérieur de l'OM n'ajustera que
        // le reliquat (reliquat = montant_total − avance_engagee). On ne pose la
        // valeur qu'une seule fois (whereNull), copie colonne à colonne.
        if ($statutOm === Workflow::STATUT_VALIDE) {
            OrdreMission::where('mission_id', $etat->mission_id)
                ->whereNull('avance_engagee')
                ->whereNotIn('statut', ['ANNULE', 'REJETE'])
                ->update(['avance_engagee' => DB::raw('avance')]);
        }

        // Clôture immédiate (SOLDE) des OM exonérés (montant nul) : ils n'entrent
        // pas dans le cycle de paiement par nature. Un OM SANS versement, en
        // revanche, devient VALIDE comme les autres — la présence de versements
        // est une donnée, pas un critère de validation.
        if ($statutOm === Workflow::STATUT_VALIDE) {
            OrdreMission::where('mission_id', $etat->mission_id)
                ->where('montant_total', '<=', 0)
                ->whereNotIn('statut', ['SOLDE', 'ANNULE'])
                ->update(['statut' => 'SOLDE']);
        }
    }

    /**
     * Champs de signature : instantanés du signataire + scellé d'intégrité.
     *
     * Le nom et la fonction imprimés viennent en priorité des Paramètres (les
     * titulaires en poste, avec leur signature manuscrite scannée) et retombent
     * sur le compte du signataire si les paramètres ne sont pas renseignés.
     * Le paraphe est ARCHIVÉ à la signature : un changement de titulaire ne
     * réimprime jamais un état déjà signé avec une autre image.
     *
     * @return array<string,mixed>
     */
    private function empreinte(EtatPaiement $etat, User $user, string $etape): array
    {
        $maintenant = now();

        [$champ, $champHash, $champImage, $posteParametre] = match ($etape) {
            Workflow::ETAPE_VERIFICATION => ['controle', 'signature_controle_hash', 'signature_controle_image', 'controleur'],
            Workflow::ETAPE_VISA_DAF => ['visa_daf', 'signature_daf_hash', 'signature_daf_image', 'daf'],
            Workflow::ETAPE_VALIDATION_DG => ['valide_dg', 'signature_dg_hash', 'signature_dg_image', 'dg'],
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
            $champImage => SignatureParametres::archiverPour($etat, $posteParametre),
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
            (string) $etat->tranche,
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
