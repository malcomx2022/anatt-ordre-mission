<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OmAmendement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Support\Permissions;
use App\Support\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MissionService
{
    public function __construct(
        private readonly CalculIndemniteService $calcul,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Retranscription SEMO : crée la mission PUIS N ordres de mission individuels
     * (participants + CAV). Pour chaque OM : calcul de l'indemnité (barème) et
     * création des versements AVANCE + RELIQUAT en statut EN_ATTENTE.
     *
     * Le tout est atomique : un doublon de reference_semo annule toute la mission.
     */
    public function creerAvecOrdres(array $data, User $user): Mission
    {
        return DB::transaction(function () use ($data, $user) {
            $mission = Mission::create([
                'objet' => $data['objet'],
                'destination' => $data['destination'],
                'zone_code' => $data['zone_code'],
                'date_depart' => $data['date_depart'],
                'date_retour' => $data['date_retour'],
                'imputation_budgetaire' => $data['imputation_budgetaire'] ?? null,
                'prise_en_charge_tiers' => (bool) ($data['prise_en_charge_tiers'] ?? false),
                'observations' => $data['observations'] ?? null,
            ]);

            $exonere = $mission->prise_en_charge_tiers;

            foreach ($data['ordres'] as $o) {
                $agent = Agent::findOrFail($o['agent_id']);

                // Catégorie reprise de SEMO si fournie, sinon celle du référentiel agent.
                $categorie = $o['categorie'] ?? $agent->categorie;

                $nbJours = $o['nb_jours'] ?? $this->calcul->nbJours($mission->date_depart, $mission->date_retour);

                // Répartition des jours par type : soit détaillée (état ANaTT), soit
                // une ligne unique déduite de type_prise_en_charge × nb_jours.
                $lignes = ! empty($o['lignes'])
                    ? array_map(fn ($l) => ['type' => $l['type'], 'nb_jours' => (int) $l['nb_jours']], $o['lignes'])
                    : [['type' => $o['type_prise_en_charge'], 'nb_jours' => $nbJours]];

                $transport = (int) ($o['montant_transport'] ?? 0);

                $calc = $this->calcul->calculerDetaille(
                    $categorie,
                    $mission->zone_code,
                    $lignes,
                    $exonere,
                    $transport,
                );

                $ordre = OrdreMission::create([
                    'reference_semo' => $o['reference_semo'],
                    'mission_id' => $mission->id,
                    'agent_id' => $agent->id,
                    'role' => $o['role'],
                    'categorie' => $categorie,
                    'zone_code' => $mission->zone_code,
                    'type_prise_en_charge' => $calc['type_principal'] ?? ($o['type_prise_en_charge'] ?? null),
                    'nb_jours' => $calc['nb_jours'],
                    'lignes_per_diem' => $calc['lignes'],
                    'montant_transport' => $calc['montant_transport'],
                    'montant_total' => $calc['montant_total'],
                    'avance' => $calc['avance'],
                    'reliquat' => $calc['reliquat'],
                    'statut' => 'IMPORTE',
                    'conducteur_a_bord' => $o['conducteur_a_bord'] ?? null,
                    'reference_autorisation' => $o['reference_autorisation'] ?? null,
                    'moyen_transport' => $o['moyen_transport'] ?? null,
                ]);

                // Versements AVANCE + RELIQUAT (sauf exonération ou montant nul).
                if (! $exonere && $ordre->montant_total > 0) {
                    foreach (['AVANCE' => $ordre->avance, 'RELIQUAT' => $ordre->reliquat] as $type => $montant) {
                        Versement::create([
                            'ordre_mission_id' => $ordre->id,
                            'type' => $type,
                            'montant' => $montant,
                            // Téléphone et opérateur figés depuis le référentiel agents.
                            'operateur' => $agent->operateur,
                            'telephone' => $agent->telephone,
                            'statut' => 'EN_ATTENTE',
                        ]);
                    }
                }

                $this->journaliser($user, 'IMPORT_OM', $ordre, [
                    'reference_semo' => $ordre->reference_semo,
                    'agent' => $agent->nom_complet,
                    'role' => $ordre->role,
                    'montant_total' => $ordre->montant_total,
                ]);
            }

            return $mission->load(['ordresMission.agent', 'ordresMission.versements']);
        });
    }

    /**
     * Validation d'un OM : IMPORTE → VALIDE, en une seule étape (le contrôleur
     * valide directement). L'OM devient verrouillé et payable.
     *
     * Voie de REPLI réservée aux missions SANS état de paiement (reprise de
     * données, correction ponctuelle) — cf. exigerAbsenceDEtat. Le circuit
     * canonique à trois signatures (contrôle → DAF → DG) se joue sur l'ÉTAT de la
     * mission (EtatPaiementService), pas OM par OM. Cf. docs/circuits-validation.md.
     */
    public function valider(OrdreMission $ordre, User $user, ?string $commentaire = null): OrdreMission
    {
        $this->exigerAbsenceDEtat($ordre, 'valider');

        $ordre = $this->transition(
            $ordre,
            ['IMPORTE'],
            Workflow::STATUT_VALIDE,
            $user,
            'VALIDATE_OM',
            $this->empreinteSignature($ordre, $user, Workflow::ETAPE_VERIFICATION, [
                'verifie_commentaire' => $commentaire,
            ]),
        );

        $this->notifications->omValide($ordre);

        return $ordre;
    }

    /**
     * Rejet d'un OM avec motif obligatoire, depuis IMPORTE (avant validation).
     * Le circuit de validation multi-signatures et ses rejets motivés vivent sur
     * l'état de paiement (EtatPaiementService).
     */
    public function rejeter(OrdreMission $ordre, string $motif, User $user): OrdreMission
    {
        $this->exigerAbsenceDEtat($ordre, 'rejeter');

        $ordre = $this->transition(
            $ordre,
            Workflow::statutsRejetables(),
            'REJETE',
            $user,
            'REJECT_OM',
            ['commentaire_rejet' => $motif],
        );
        $this->notifications->omRejete($ordre);

        return $ordre;
    }

    /**
     * Amendement d'un OM : nouveau nombre de jours facturés (rallonge ou
     * réduction) après retranscription. Recalcule le montant au barème puis
     * répartit avance/reliquat.
     *
     * - Avant l'engagement de l'avance (avance_engagee null) : recalcul complet
     *   70/30 (les deux versements sont réajustés).
     * - Après (avance figée) : l'avance ne bouge plus, le RELIQUAT absorbe l'écart
     *   et peut devenir NÉGATIF (trop-perçu, exclu des fichiers).
     *
     * Périmètre v1 : refusé dès que l'état des reliquats est établi/validé, ou
     * qu'un état de tranche est en cours de signature. Cf. docs/amendement-om.md.
     */
    public function amender(OrdreMission $ordre, array $data, User $user): OrdreMission
    {
        $ordre = DB::transaction(function () use ($ordre, $data, $user) {
            $ordre = OrdreMission::lockForUpdate()->findOrFail($ordre->id);

            if (in_array($ordre->statut, ['REJETE', 'ANNULE'], true)) {
                throw ValidationException::withMessages([
                    'statut' => ["Un OM au statut « {$ordre->statut} » ne peut pas être amendé."],
                ]);
            }
            $this->exigerAmendementPossible($ordre);

            $exonere = (bool) ($ordre->mission?->prise_en_charge_tiers);

            // Nouvelles lignes : détaillées si fournies, sinon le type courant de
            // l'OM × le nouveau nombre de jours.
            $lignes = ! empty($data['lignes'])
                ? array_map(fn ($l) => ['type' => $l['type'], 'nb_jours' => (int) $l['nb_jours']], $data['lignes'])
                : [['type' => $ordre->type_prise_en_charge, 'nb_jours' => (int) $data['nb_jours']]];

            $calc = $this->calcul->calculerDetaille(
                $ordre->categorie,
                $ordre->zone_code,
                $lignes,
                $exonere,
                (int) $ordre->montant_transport,
            );

            // Instantané AVANT.
            $avant = [
                'nb_jours' => (int) $ordre->nb_jours,
                'montant_total' => (int) $ordre->montant_total,
                'lignes' => $ordre->lignes_per_diem,
            ];

            // Répartition : l'avance engagée (si posée) prime ; sinon 70/30.
            [$avance, $reliquat] = $this->calcul->repartir($calc['montant_total'], $ordre->avance_engagee);

            $ordre->update([
                'type_prise_en_charge' => $calc['type_principal'] ?? $ordre->type_prise_en_charge,
                'nb_jours' => $calc['nb_jours'],
                'lignes_per_diem' => $calc['lignes'],
                'montant_total' => $calc['montant_total'],
                'avance' => $avance,
                'reliquat' => $reliquat,
            ]);

            $this->reventilerVersements($ordre, $avance, $reliquat);

            OmAmendement::create([
                'ordre_mission_id' => $ordre->id,
                'nb_jours_avant' => $avant['nb_jours'],
                'nb_jours_apres' => $calc['nb_jours'],
                'lignes_avant' => $avant['lignes'],
                'montant_total_avant' => $avant['montant_total'],
                'montant_total_apres' => $calc['montant_total'],
                'delta_montant' => $calc['montant_total'] - $avant['montant_total'],
                'motif' => (string) $data['motif'],
                'amende_par' => $user->id,
                'amende_par_nom' => $user->name,
                'amende_le' => now(),
            ]);

            $this->journaliser($user, 'AMEND_OM', $ordre, [
                'reference_semo' => $ordre->reference_semo,
                'nb_jours' => $calc['nb_jours'],
                'montant_total' => $calc['montant_total'],
                'delta' => $calc['montant_total'] - $avant['montant_total'],
                'reliquat' => $reliquat,
                'motif' => (string) $data['motif'],
            ]);

            return $ordre->fresh(['agent', 'mission', 'versements']);
        });

        // Hors transaction : un amendement qui produit un trop-perçu (reliquat
        // négatif) ouvre une régularisation — l'agent financier en est averti.
        if ($ordre->reliquat < 0) {
            $this->notifications->tropPercuCree($ordre, abs((int) $ordre->reliquat));
        }

        return $ordre;
    }

    /**
     * Répercute la nouvelle répartition sur les versements non confirmés.
     * Le RELIQUAT suit toujours ; l'AVANCE n'est réajustée que tant qu'elle n'est
     * pas engagée (avant validation des avances).
     */
    private function reventilerVersements(OrdreMission $ordre, int $avance, int $reliquat): void
    {
        // Un reliquat négatif (trop-perçu) bascule le versement en A_RECOUVRER :
        // il sort du périmètre payable et rejoint les régularisations. Un reliquat
        // redevenu ≥ 0 (nouvel amendement) repasse EN_ATTENTE.
        $ordre->versements()
            ->where('type', 'RELIQUAT')
            ->whereNotIn('statut', ['CONFIRME', 'GENERE'])
            ->update([
                'montant' => $reliquat,
                'statut' => $reliquat < 0 ? 'A_RECOUVRER' : 'EN_ATTENTE',
            ]);

        if ($ordre->avance_engagee === null) {
            $ordre->versements()
                ->where('type', 'AVANCE')
                ->where('statut', '!=', 'CONFIRME')
                ->update(['montant' => $avance]);
        }
    }

    /**
     * Garde d'éligibilité de l'amendement (périmètre v1) : on n'amende pas un
     * document en cours de signature ni un reliquat déjà établi/validé.
     */
    private function exigerAmendementPossible(OrdreMission $ordre): void
    {
        // Trop-perçu déjà RECOUVRÉ (retenue sur salaire appliquée par la RH) :
        // la situation est soldée hors application. Ré-amender rouvrirait un
        // versement clos et ignorerait le montant déjà retenu (risque de
        // sur-paiement). C'est un avenant formel — hors périmètre v1.
        // (Un trop-perçu encore A_RECOUVRER, lui, reste amendable : rien n'a été retenu.)
        if ($ordre->versements()->where('type', 'RELIQUAT')->where('statut', 'RECOUVRE')->exists()) {
            throw ValidationException::withMessages([
                'versement' => ['Le trop-perçu de cet ordre de mission a déjà été recouvré (retenue appliquée) : un nouvel amendement relève d\'un avenant formel (hors périmètre).'],
            ]);
        }

        $etats = EtatPaiement::where('mission_id', $ordre->mission_id)->get()->keyBy('tranche');

        $avance = $etats->get(EtatPaiement::TRANCHE_AVANCE);
        if ($avance && in_array($avance->statut, ['CONTROLE', 'VISA_DAF'], true)) {
            throw ValidationException::withMessages([
                'etat' => ["L'état des avances est en cours de signature (« {$avance->statut} ») : rejetez-le avant d'amender."],
            ]);
        }

        $reliquat = $etats->get(EtatPaiement::TRANCHE_RELIQUAT);
        // En cours de signature (CONTROLE / VISA_DAF), le document n'est pas
        // définitif : on peut le REJETER pour l'amender, puis le rétablir. On
        // guide donc vers ce rejet plutôt que de bloquer sèchement.
        if ($reliquat && in_array($reliquat->statut, ['CONTROLE', 'VISA_DAF'], true)) {
            throw ValidationException::withMessages([
                'etat' => ["L'état des reliquats est en cours de signature (« {$reliquat->statut} ») : rejetez-le avant d'amender, puis rétablissez-le."],
            ]);
        }
        // Validé et scellé (HMAC) : il ne peut plus être rejeté par le circuit.
        // Un amendement postérieur relève d'un avenant formel — hors périmètre v1.
        if ($reliquat && $reliquat->estValide()) {
            throw ValidationException::withMessages([
                'etat' => ["L'état des reliquats est validé et scellé : un amendement à ce stade relève d'un avenant formel (hors périmètre)."],
            ]);
        }
    }

    /**
     * Le circuit se joue sur l'état de paiement de la mission dès lors qu'il en
     * existe un : agir OM par OM ferait diverger le statut de l'OM de celui de
     * l'état signé. Les transitions unitaires restent donc réservées aux missions
     * sans état (reprise de données, correction ponctuelle).
     *
     * C'est le verrou d'exclusivité entre les deux circuits de validation.
     *
     * @see docs/circuits-validation.md
     */
    private function exigerAbsenceDEtat(OrdreMission $ordre, string $action): void
    {
        $etat = EtatPaiement::where('mission_id', $ordre->mission_id)->first();

        if ($etat !== null) {
            throw ValidationException::withMessages([
                'etat' => [
                    "Cette mission possède un état de paiement (statut « {$etat->statut} ») : "
                    ."l'action « {$action} » doit être effectuée sur l'état, pas sur l'OM.",
                ],
            ]);
        }
    }

    /**
     * Champs d'une signature : instantanés du signataire + scellé d'intégrité.
     *
     * Le nom et la fonction sont figés au moment de la signature : un renommage,
     * une mutation ou la suppression du compte ne doit pas réécrire un document
     * déjà signé.
     *
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    private function empreinteSignature(OrdreMission $ordre, User $user, string $etape, array $extra = []): array
    {
        $maintenant = now();
        $hash = Workflow::sceller($ordre, $user, $etape, $maintenant->toISOString());

        $prefixe = match ($etape) {
            Workflow::ETAPE_VERIFICATION => ['verifie', 'signature_verif_hash'],
            Workflow::ETAPE_VISA_DAF => ['visa_daf', 'signature_daf_hash'],
            Workflow::ETAPE_VALIDATION_DG => ['valide_dg', 'signature_dg_hash'],
        };

        [$champ, $champHash] = $prefixe;

        return array_merge([
            "{$champ}_par" => $user->id,
            "{$champ}_le" => $maintenant,
            "{$champ}_nom" => $user->name,
            "{$champ}_fonction" => $user->fonction ?: Permissions::LIBELLES[$user->role] ?? null,
            $champHash => $hash,
        ], array_filter($extra, fn ($v) => $v !== null));
    }

    /**
     * Transition de statut atomique avec verrou pessimiste (anti race condition).
     *
     * @param  array<int,string>  $statutsAttendus
     * @param  array<string,mixed>  $champsSupplementaires
     */
    private function transition(
        OrdreMission $ordre,
        array $statutsAttendus,
        string $nouveauStatut,
        User $user,
        string $action,
        array $champsSupplementaires = [],
    ): OrdreMission {
        return DB::transaction(function () use ($ordre, $statutsAttendus, $nouveauStatut, $user, $action, $champsSupplementaires) {
            $ordre = OrdreMission::lockForUpdate()->findOrFail($ordre->id);

            if (! in_array($ordre->statut, $statutsAttendus, true)) {
                throw ValidationException::withMessages([
                    'statut' => ["Cet OM (statut « {$ordre->statut} ») ne peut pas passer à « {$nouveauStatut} »."],
                ]);
            }

            $ordre->update(array_merge(['statut' => $nouveauStatut], $champsSupplementaires));

            $this->journaliser($user, $action, $ordre, [
                'reference_semo' => $ordre->reference_semo,
                'statut' => $nouveauStatut,
            ]);

            return $ordre->fresh(['agent', 'mission', 'versements']);
        });
    }

    /** Écrit une entrée dans le journal d'audit. */
    private function journaliser(User $user, string $action, OrdreMission $ordre, array $donnees = []): void
    {
        AuditService::log($user, $action, 'ordre_mission', $ordre->id, $donnees);
    }
}
