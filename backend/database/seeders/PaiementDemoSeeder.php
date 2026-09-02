<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Paiement\MoovFileGenerator;
use App\Services\Paiement\MtnFileGenerator;
use App\Support\Parametres;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Données de démonstration « cycle complet » : missions entièrement traitées avec
 * versements CONFIRMÉS regroupés en lots MTN/Moov.
 *
 * Les fichiers de paiement sont produits par les VRAIS générateurs (MtnFileGenerator
 * / MoovFileGenerator) : les lots seedés sont donc réellement téléchargeables, avec un
 * nom_fichier pointant vers un fichier physique (storage/app/paiements/…), comme via l'app.
 *
 * date_paiement = now() à la confirmation => données toujours dans le mois courant
 * (dashboard + états mensuels peuplés) à chaque migrate:fresh --seed.
 */
class PaiementDemoSeeder extends Seeder
{
    private ?int $gestionId = null;

    private ?int $controleId = null;

    public function run(): void
    {
        $finance = User::where('email', 'finance@anatt.bj')->first();
        $this->gestionId = User::where('email', 'gestion@anatt.bj')->value('id');
        $this->controleId = User::where('email', 'controle@anatt.bj')->value('id');
        $mtn = app(MtnFileGenerator::class);
        $moov = app(MoovFileGenerator::class);

        $missions = [
            [
                'objet' => 'Atelier régional sur la sécurité routière',
                'destination' => 'PARAKOU', 'zone_code' => 'INTERIEUR',
                'imputation' => 'ANATT-2026-DT-021', 'nb_jours' => 3,
                'oms' => [
                    ['ref' => '0541405640', 'matricule' => 'ANT-1007', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_1', 'type' => 'JOURNEE_COMPLETE'],
                    ['ref' => '0541405641', 'matricule' => 'ANT-1003', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_2', 'type' => 'JOURNEE_COMPLETE'],
                    ['ref' => '0541405642', 'matricule' => 'ANT-1006', 'role' => 'CAV', 'categorie' => 'ORDRE_3', 'type' => 'JOURNEE_COMPLETE'],
                ],
            ],
            [
                'objet' => 'Coordination sous-régionale des transports',
                'destination' => 'LOMÉ (TOGO)', 'zone_code' => 'EXT_ZONE_I',
                'imputation' => 'ANATT-2026-DG-007', 'nb_jours' => 2,
                'oms' => [
                    ['ref' => '0541405643', 'matricule' => 'ANT-1004', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_2', 'type' => 'LOGE_NOURRI'],
                    ['ref' => '0541405644', 'matricule' => 'ANT-1005', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_3', 'type' => 'LOGE_NOURRI'],
                ],
            ],
            [
                'objet' => 'Inspection des gares routières',
                'destination' => 'ABOMEY', 'zone_code' => 'INTERIEUR',
                'imputation' => 'ANATT-2026-DT-022', 'nb_jours' => 1,
                'oms' => [
                    ['ref' => '0541405645', 'matricule' => 'ANT-1001', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_1', 'type' => 'DEUX_REPAS'],
                    ['ref' => '0541405646', 'matricule' => 'ANT-1002', 'role' => 'CAV', 'categorie' => 'ORDRE_3', 'type' => 'DEUX_REPAS'],
                ],
            ],
        ];

        foreach ($missions as $m) {
            $missionId = $this->creerMissionEtOms($m);

            // Génération RÉELLE des fichiers (lot GENERE + versements GENERE) puis confirmation.
            $operateurs = DB::table('ordre_missions')
                ->join('agents', 'ordre_missions.agent_id', '=', 'agents.id')
                ->where('ordre_missions.mission_id', $missionId)
                ->distinct()->pluck('agents.operateur');

            foreach (['AVANCE', 'RELIQUAT'] as $tranche) {
                foreach ($operateurs as $operateur) {
                    $generateur = $operateur === 'MTN' ? $mtn : $moov;
                    try {
                        $res = $generateur->generer($missionId, $tranche, $finance);
                        $this->confirmerLot((int) $res['lot']->id, (int) $finance->id);
                    } catch (ValidationException) {
                        // Aucun versement éligible pour cet opérateur/tranche : on ignore.
                    }
                }
            }

            $this->soldeOms($missionId);
        }
    }

    /** Crée la mission, ses OM (statut VALIDE) et les versements AVANCE/RELIQUAT (EN_ATTENTE). */
    private function creerMissionEtOms(array $m): int
    {
        $now = now();
        $depart = $now->copy()->subDays(20);
        $retour = $depart->copy()->addDays($m['nb_jours'] - 1);

        DB::table('missions')->updateOrInsert(
            ['objet' => $m['objet'], 'destination' => $m['destination']],
            [
                'zone_code' => $m['zone_code'],
                'date_depart' => $depart->toDateString(),
                'date_retour' => $retour->toDateString(),
                'imputation_budgetaire' => $m['imputation'],
                'prise_en_charge_tiers' => false,
                'observations' => 'Mission de démonstration — cycle de paiement complet (fichiers générés et confirmés).',
                'created_at' => $now, 'updated_at' => $now,
            ]
        );
        $missionId = DB::table('missions')->where('objet', $m['objet'])->where('destination', $m['destination'])->value('id');

        foreach ($m['oms'] as $om) {
            $agent = DB::table('agents')->where('matricule', $om['matricule'])->first();
            $taux = (int) DB::table('baremes')
                ->where('categorie', $om['categorie'])
                ->where('zone_code', $m['zone_code'])
                ->where('type_prise_en_charge', $om['type'])
                ->value('taux_journalier');

            $total = $taux * $m['nb_jours'];
            $avance = (int) round($total * Parametres::partAvance() / 100);   // part paramétrable (70 % par défaut)
            $reliquat = $total - $avance;

            DB::table('ordre_missions')->updateOrInsert(
                ['reference_semo' => $om['ref']],
                [
                    'mission_id' => $missionId,
                    'agent_id' => $agent->id,
                    'role' => $om['role'],
                    'categorie' => $om['categorie'],
                    'zone_code' => $m['zone_code'],
                    'type_prise_en_charge' => $om['type'],
                    'nb_jours' => $m['nb_jours'],
                    'montant_total' => $total,
                    'avance' => $avance,
                    'reliquat' => $reliquat,
                    'statut' => 'VALIDE',                 // validé, prêt au paiement
                    'reference_autorisation' => 'AUT-2026-'.substr($om['ref'], -4),
                    'moyen_transport' => 'Véhicule administratif',
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
            $omId = DB::table('ordre_missions')->where('reference_semo', $om['ref'])->value('id');

            // Journal d'audit de l'OM : retranscription puis validation (avant paiement).
            $nomComplet = trim($agent->nom.' '.$agent->prenoms);
            $this->journaliserOm((int) $omId, 'IMPORT_OM', $this->gestionId, [
                'reference_semo' => $om['ref'], 'agent' => $nomComplet, 'role' => $om['role'], 'montant_total' => $total,
            ], $now->copy()->subSeconds(3));
            $this->journaliserOm((int) $omId, 'VALIDATE_OM', $this->controleId, [], $now->copy()->subSeconds(2));

            foreach (['AVANCE' => $avance, 'RELIQUAT' => $reliquat] as $type => $montant) {
                DB::table('versements')->updateOrInsert(
                    ['ordre_mission_id' => $omId, 'type' => $type],
                    [
                        'montant' => $montant,
                        'operateur' => $agent->operateur,
                        'telephone' => $agent->telephone,
                        'statut' => 'EN_ATTENTE',
                        'lot_id' => null,
                        'date_paiement' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ]
                );
            }
        }

        return (int) $missionId;
    }

    /** Confirme un lot et ses versements (date_paiement = maintenant) sans notification. */
    private function confirmerLot(int $lotId, int $financeId): void
    {
        $now = now();
        DB::table('versements')->where('lot_id', $lotId)->where('statut', '!=', 'CONFIRME')
            ->update(['statut' => 'CONFIRME', 'date_paiement' => $now, 'updated_at' => $now]);
        $lot = DB::table('lots_paiement')->where('id', $lotId)->first();
        DB::table('lots_paiement')->where('id', $lotId)
            ->update(['statut' => 'CONFIRME', 'confirme_le' => $now, 'confirme_par' => $financeId, 'updated_at' => $now]);

        // Audit de la confirmation (alimente le journal d'activité).
        DB::table('audit_logs')->insert([
            'user_id' => $financeId,
            'action' => 'CONFIRM_LOT',
            'entite' => 'lot_paiement',
            'entite_id' => $lotId,
            'donnees' => json_encode([
                'tranche' => $lot->tranche, 'operateur' => $lot->operateur, 'montant_total' => $lot->montant_total,
            ], JSON_UNESCAPED_UNICODE),
            'ip_address' => '127.0.0.1',
            'created_at' => $now,
        ]);
    }

    /** Passe à SOLDE les OM dont tous les versements sont confirmés. */
    private function soldeOms(int $missionId): void
    {
        $omIds = DB::table('ordre_missions')->where('mission_id', $missionId)->pluck('id');
        foreach ($omIds as $omId) {
            $reste = DB::table('versements')->where('ordre_mission_id', $omId)->where('statut', '!=', 'CONFIRME')->exists();
            if (! $reste) {
                DB::table('ordre_missions')->where('id', $omId)->update(['statut' => 'SOLDE', 'updated_at' => now()]);
            }
        }
    }

    /** Insère une entrée d'audit pour un OM (alimente l'écran d'historique). */
    private function journaliserOm(int $omId, string $action, ?int $userId, array $donnees, $createdAt): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'entite' => 'ordre_mission',
            'entite_id' => $omId,
            'donnees' => json_encode($donnees, JSON_UNESCAPED_UNICODE),
            'ip_address' => '127.0.0.1',
            'created_at' => $createdAt,
        ]);
    }
}
