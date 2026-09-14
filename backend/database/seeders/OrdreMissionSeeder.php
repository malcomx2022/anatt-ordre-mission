<?php

namespace Database\Seeders;

use App\Support\Parametres;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrdreMissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $gestionId = DB::table('users')->where('email', 'gestion@anatt.bj')->value('id');

        $missionId = DB::table('missions')
            ->where('destination', 'BOHICON')
            ->value('id');

        $dada = DB::table('agents')->where('matricule', 'ANT-1001')->value('id');   // DADA Richard (INDICE_SUP_800)
        $cav = DB::table('agents')->where('matricule', 'ANT-1002')->value('id');     // AKPACA Gérard (CAV, INDICE_INF_400)

        // nb_jours selon la règle des 12 h : 12/11 -> 14/11 = 3 jours entiers.
        $nbJours = 3;

        $oms = [
            [
                'reference_semo' => '0541405636',
                'agent_id' => $dada,
                'role' => 'PARTICIPANT',
                'categorie' => 'INDICE_SUP_800',
                'type_prise_en_charge' => 'JOURNEE_COMPLETE',
                // Mention logistique : le conducteur à bord est payé via SON propre OM (ci-dessous).
                'conducteur_a_bord' => 'AKPACA Gérard',
                'reference_autorisation' => 'AUT-2025-0114',
                'moyen_transport' => 'Véhicule administratif',
            ],
            [
                'reference_semo' => '0541405637',
                'agent_id' => $cav,
                'role' => 'CAV',
                'categorie' => 'INDICE_INF_400',
                'type_prise_en_charge' => 'JOURNEE_COMPLETE',
                'conducteur_a_bord' => null,
                'reference_autorisation' => 'AUT-2025-0114',
                'moyen_transport' => 'Véhicule administratif',
            ],
        ];

        foreach ($oms as $om) {
            // Taux journalier exact depuis le barème (catégorie × zone × type).
            $taux = (int) DB::table('baremes')
                ->where('categorie', $om['categorie'])
                ->where('zone_code', 'INTERIEUR')
                ->where('type_prise_en_charge', $om['type_prise_en_charge'])
                ->value('taux_journalier');

            $total = $taux * $nbJours;
            $avance = (int) round($total * Parametres::partAvance() / 100);   // part paramétrable (70 % par défaut)
            $reliquat = $total - $avance;

            DB::table('ordre_missions')->updateOrInsert(
                ['reference_semo' => $om['reference_semo']],
                array_merge($om, [
                    'mission_id' => $missionId,
                    'zone_code' => 'INTERIEUR',
                    'nb_jours' => $nbJours,
                    'montant_total' => $total,
                    'avance' => $avance,
                    'reliquat' => $reliquat,
                    'statut' => 'IMPORTE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

            // Versements AVANCE + RELIQUAT (EN_ATTENTE) — téléphone/opérateur depuis le référentiel agent.
            $omId = DB::table('ordre_missions')->where('reference_semo', $om['reference_semo'])->value('id');
            $agent = DB::table('agents')->where('id', $om['agent_id'])->first();

            // Journal d'audit : retranscription (OM au statut IMPORTE, en attente de validation).
            DB::table('audit_logs')->insert([
                'user_id' => $gestionId,
                'action' => 'IMPORT_OM',
                'entite' => 'ordre_mission',
                'entite_id' => $omId,
                'donnees' => json_encode([
                    'reference_semo' => $om['reference_semo'],
                    'agent' => trim($agent->nom.' '.$agent->prenoms),
                    'role' => $om['role'],
                    'montant_total' => $total,
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => '127.0.0.1',
                'created_at' => $now,
            ]);

            foreach (['AVANCE' => $avance, 'RELIQUAT' => $reliquat] as $type => $montant) {
                DB::table('versements')->updateOrInsert(
                    ['ordre_mission_id' => $omId, 'type' => $type],
                    [
                        'montant' => $montant,
                        'operateur' => $agent->operateur,
                        'telephone' => $agent->telephone,
                        'statut' => 'EN_ATTENTE',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
