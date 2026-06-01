<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Données de démonstration « cycle complet » : missions entièrement traitées avec
 * versements CONFIRMÉS (date_paiement = maintenant) regroupés en lots MTN/Moov.
 *
 * Objectif : peupler le tableau de bord et les états du MOIS COURANT (les rapports
 * s'appuient sur versements.date_paiement). En utilisant now(), les données tombent
 * toujours dans le mois en cours, à chaque migrate:fresh --seed.
 */
class PaiementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $finance = User::where('email', 'finance@anatt.bj')->value('id');

        // Mission interne (PARAKOU) — participants MTN + CAV Moov.
        $this->seedMissionPayee([
            'objet' => 'Atelier régional sur la sécurité routière',
            'destination' => 'PARAKOU',
            'zone_code' => 'INTERIEUR',
            'imputation' => 'ANATT-2026-DT-021',
            'nb_jours' => 3,
            'oms' => [
                ['ref' => '0541405640', 'matricule' => 'ANT-1007', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_1', 'type' => 'JOURNEE_COMPLETE'],
                ['ref' => '0541405641', 'matricule' => 'ANT-1003', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_2', 'type' => 'JOURNEE_COMPLETE'],
                ['ref' => '0541405642', 'matricule' => 'ANT-1006', 'role' => 'CAV', 'categorie' => 'ORDRE_3', 'type' => 'JOURNEE_COMPLETE'],
            ],
        ], $finance);

        // Mission extérieure (LOMÉ, EXT_ZONE_I) — logé/nourri.
        $this->seedMissionPayee([
            'objet' => 'Coordination sous-régionale des transports',
            'destination' => 'LOMÉ (TOGO)',
            'zone_code' => 'EXT_ZONE_I',
            'imputation' => 'ANATT-2026-DG-007',
            'nb_jours' => 2,
            'oms' => [
                ['ref' => '0541405643', 'matricule' => 'ANT-1004', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_2', 'type' => 'LOGE_NOURRI'],
                ['ref' => '0541405644', 'matricule' => 'ANT-1005', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_3', 'type' => 'LOGE_NOURRI'],
            ],
        ], $finance);

        // Mission courte (ABOMEY) — deux repas.
        $this->seedMissionPayee([
            'objet' => 'Inspection des gares routières',
            'destination' => 'ABOMEY',
            'zone_code' => 'INTERIEUR',
            'imputation' => 'ANATT-2026-DT-022',
            'nb_jours' => 1,
            'oms' => [
                ['ref' => '0541405645', 'matricule' => 'ANT-1001', 'role' => 'PARTICIPANT', 'categorie' => 'ORDRE_1', 'type' => 'DEUX_REPAS'],
                ['ref' => '0541405646', 'matricule' => 'ANT-1002', 'role' => 'CAV', 'categorie' => 'ORDRE_3', 'type' => 'DEUX_REPAS'],
            ],
        ], $finance);
    }

    /** Crée une mission, ses OM (SOLDE) et confirme tous les versements via des lots. */
    private function seedMissionPayee(array $m, ?int $finance): void
    {
        $now = now();
        $paiement = $now->copy();                       // date_paiement dans le mois courant
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
                'observations' => 'Mission de démonstration — cycle de paiement complet (versements confirmés).',
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
                    'statut' => 'SOLDE',                // tous versements confirmés => OM soldé
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
                        'created_at' => $now, 'updated_at' => $now,
                    ]
                );
            }
        }

        // Regroupement en lots (1 mission × 1 tranche × 1 opérateur) puis confirmation.
        foreach (['AVANCE', 'RELIQUAT'] as $tranche) {
            foreach (['MTN', 'MOOV'] as $operateur) {
                $versements = DB::table('versements')
                    ->join('ordre_missions', 'versements.ordre_mission_id', '=', 'ordre_missions.id')
                    ->where('ordre_missions.mission_id', $missionId)
                    ->where('versements.type', $tranche)
                    ->where('versements.operateur', $operateur)
                    ->select('versements.id', 'versements.montant')
                    ->get();

                if ($versements->isEmpty()) {
                    continue;
                }

                $ext = $operateur === 'MTN' ? 'txt' : 'xlsx';
                $fichier = "{$operateur}_{$missionId}_{$tranche}.{$ext}";

                DB::table('lots_paiement')->updateOrInsert(
                    ['mission_id' => $missionId, 'tranche' => $tranche, 'operateur' => $operateur],
                    [
                        'statut' => 'CONFIRME',
                        'nom_fichier' => $fichier,
                        'nb_lignes' => $versements->count(),
                        'montant_total' => (int) $versements->sum('montant'),
                        'genere_le' => $now, 'genere_par' => $finance,
                        'confirme_le' => $paiement, 'confirme_par' => $finance,
                        'created_at' => $now, 'updated_at' => $now,
                    ]
                );
                $lotId = DB::table('lots_paiement')
                    ->where('mission_id', $missionId)->where('tranche', $tranche)->where('operateur', $operateur)
                    ->value('id');

                DB::table('versements')->whereIn('id', $versements->pluck('id'))->update([
                    'lot_id' => $lotId,
                    'statut' => 'CONFIRME',
                    'date_paiement' => $paiement,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
