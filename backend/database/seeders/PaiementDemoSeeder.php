<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Paiement\MoovFileGenerator;
use App\Services\Paiement\MtnFileGenerator;
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
    public function run(): void
    {
        $finance = User::where('email', 'finance@anatt.bj')->first();
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
            $avance = (int) round($total / 3);
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
        DB::table('lots_paiement')->where('id', $lotId)
            ->update(['statut' => 'CONFIRME', 'confirme_le' => $now, 'confirme_par' => $financeId, 'updated_at' => $now]);
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
}
