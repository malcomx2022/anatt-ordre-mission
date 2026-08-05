<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\Mission;
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
                    'type_prise_en_charge' => $calc['type_principal'] ?? $o['type_prise_en_charge'],
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

    /** Validation d'un OM : IMPORTE → VALIDE. */
    public function valider(OrdreMission $ordre, User $user, ?string $commentaire = null): OrdreMission
    {
        $this->exigerAbsenceDEtat($ordre, 'valider');

        $cible = Workflow::statutApresControle();

        $ordre = $this->transition(
            $ordre,
            ['IMPORTE'],
            $cible,
            $user,
            $cible === Workflow::STATUT_VALIDE ? 'VALIDATE_OM' : 'VERIFY_OM',
            $this->empreinteSignature($ordre, $user, Workflow::ETAPE_VERIFICATION, [
                'verifie_commentaire' => $commentaire,
            ]),
        );

        // Le circuit complet n'est pas terminé : c'est la validation finale du DG
        // qui notifie le bénéficiaire. Ici on prévient le DAF que la main lui passe.
        if ($cible === Workflow::STATUT_VALIDE) {
            $this->notifications->omValide($ordre);
        }

        return $ordre;
    }

    /**
     * Visa du DAF (étape 2 sur 3) : VERIFIE → VISA_DAF.
     * L'OM n'est pas encore payable — seul le DG verrouille.
     */
    public function viser(OrdreMission $ordre, User $user, ?string $commentaire = null): OrdreMission
    {
        $this->exigerAbsenceDEtat($ordre, 'viser');
        $this->exigerCircuitComplet('viser');

        // Séparation des tâches : le visa DAF revient à une autre personne que
        // celle qui a vérifié (contrôleur), sinon deux signatures pour une seule main.
        if (Workflow::separationDesTaches() && (int) $ordre->verifie_par === (int) $user->id) {
            throw ValidationException::withMessages([
                'signataire' => ['Vous avez vérifié cet OM : le visa DAF revient à une autre personne.'],
            ]);
        }

        return $this->transition(
            $ordre,
            [Workflow::statutAvantVisaDaf()],
            Workflow::STATUT_VISA_DAF,
            $user,
            'VISA_DAF_OM',
            $this->empreinteSignature($ordre, $user, Workflow::ETAPE_VISA_DAF, [
                'visa_daf_commentaire' => $commentaire,
            ]),
        );
    }

    /**
     * Validation finale du DG (étape 3 sur 3) : VISA_DAF → VALIDE.
     * C'est cette étape qui VERROUILLE l'OM et le rend payable.
     */
    public function approuver(OrdreMission $ordre, User $user): OrdreMission
    {
        $this->exigerAbsenceDEtat($ordre, 'approuver');
        $this->exigerCircuitComplet('approuver');

        // Séparation des tâches : la validation finale revient à une personne
        // distincte des DEUX signataires amont (contrôleur ET DAF).
        if (Workflow::separationDesTaches()
            && in_array((int) $user->id, [(int) $ordre->verifie_par, (int) $ordre->visa_daf_par], true)) {
            throw ValidationException::withMessages([
                'signataire' => ['Vous êtes déjà intervenu sur cet OM (vérification ou visa DAF) : la validation finale revient à une autre personne.'],
            ]);
        }

        $ordre = $this->transition(
            $ordre,
            [Workflow::statutAvantValidationDg()],
            Workflow::STATUT_VALIDE,
            $user,
            'APPROVE_DG_OM',
            $this->empreinteSignature($ordre, $user, Workflow::ETAPE_VALIDATION_DG),
        );

        $this->notifications->omValide($ordre);

        return $ordre;
    }

    /**
     * Rejet d'un OM avec motif obligatoire. Possible à chacune des étapes du
     * circuit ; le rejet efface les signatures amont, qui n'ont plus d'objet.
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
            [
                'commentaire_rejet' => $motif,
                // Les visas obtenus portaient sur un OM désormais rejeté.
                'verifie_par' => null, 'verifie_le' => null, 'verifie_nom' => null,
                'verifie_fonction' => null, 'signature_verif_hash' => null,
                'visa_daf_par' => null, 'visa_daf_le' => null, 'visa_daf_nom' => null,
                'visa_daf_fonction' => null, 'signature_daf_hash' => null,
            ],
        );
        $this->notifications->omRejete($ordre);

        return $ordre;
    }

    /**
     * Le circuit se joue sur l'état de paiement de la mission dès lors qu'il en
     * existe un : agir OM par OM ferait diverger le statut de l'OM de celui de
     * l'état signé. Les transitions unitaires restent donc réservées aux missions
     * sans état (reprise de données, correction ponctuelle).
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

    /** Les étapes DAF et DG n'existent que si le circuit complet est actif. */
    private function exigerCircuitComplet(string $action): void
    {
        if (! Workflow::doubleValidationActive()) {
            throw ValidationException::withMessages([
                'workflow' => ["Le circuit de double validation est désactivé : l'action « {$action} » n'est pas disponible."],
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
