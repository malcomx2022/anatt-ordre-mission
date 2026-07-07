<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
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
    public function valider(OrdreMission $ordre, User $user): OrdreMission
    {
        $ordre = $this->transition($ordre, ['IMPORTE'], 'VALIDE', $user, 'VALIDATE_OM');
        $this->notifications->omValide($ordre);

        return $ordre;
    }

    /** Rejet d'un OM avec motif obligatoire : IMPORTE → REJETE. */
    public function rejeter(OrdreMission $ordre, string $motif, User $user): OrdreMission
    {
        $ordre = $this->transition($ordre, ['IMPORTE'], 'REJETE', $user, 'REJECT_OM', [
            'commentaire_rejet' => $motif,
        ]);
        $this->notifications->omRejete($ordre);

        return $ordre;
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
