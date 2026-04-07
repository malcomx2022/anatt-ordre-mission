<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'ADMIN')->first();
        $chef = User::where('role', 'CHEF_SERVICE')->first();
        $dg = User::where('role', 'DIRECTEUR')->first();
        $drh = User::where('role', 'DRH')->first();

        $logs = [
            ['user' => $admin, 'action' => 'LOGIN', 'cible' => 'session', 'details' => ['ip' => '192.168.1.100'], 'ip' => '192.168.1.100', 'ago' => 0],
            ['user' => $admin, 'action' => 'CREATE_ORDRE_MISSION', 'cible' => 'ordre_mission:1', 'details' => ['numero' => 'OM-2026-0001', 'agent' => 'Jean KOUASSI', 'destination' => 'Parakou'], 'ip' => '192.168.1.100', 'ago' => 1],
            ['user' => $admin, 'action' => 'SUBMIT_ORDRE_MISSION', 'cible' => 'ordre_mission:1', 'details' => ['numero' => 'OM-2026-0001', 'statut' => 'BROUILLON -> SOUMIS'], 'ip' => '192.168.1.100', 'ago' => 1],
            ['user' => $chef, 'action' => 'VALIDATE_N1', 'cible' => 'ordre_mission:1', 'details' => ['numero' => 'OM-2026-0001', 'statut' => 'SOUMIS -> VALIDE_N1'], 'ip' => '192.168.1.105', 'ago' => 2],
            ['user' => $dg, 'action' => 'APPROVE_DG', 'cible' => 'ordre_mission:1', 'details' => ['numero' => 'OM-2026-0001', 'statut' => 'VALIDE_N1 -> APPROUVE_DG'], 'ip' => '192.168.1.110', 'ago' => 3],
            ['user' => $drh, 'action' => 'GENERATE_PAYMENT', 'cible' => 'paiement:moov', 'details' => ['fichier' => 'MOOV_20260315_001.csv', 'nb_paiements' => 3, 'montant_total' => 375000], 'ip' => '192.168.1.115', 'ago' => 4],
            ['user' => $admin, 'action' => 'CREATE_AGENT', 'cible' => 'agent:8', 'details' => ['matricule' => 'ANT-008', 'nom' => 'BOKO Grace'], 'ip' => '192.168.1.100', 'ago' => 5],
            ['user' => $admin, 'action' => 'UPDATE_TAUX', 'cible' => 'taux:1', 'details' => ['categorie' => 'A1', 'ancien' => 22000, 'nouveau' => 25000], 'ip' => '192.168.1.100', 'ago' => 10],
            ['user' => $chef, 'action' => 'REJECT_ORDRE_MISSION', 'cible' => 'ordre_mission:8', 'details' => ['numero' => 'OM-2026-0008', 'motif' => 'Budget insuffisant'], 'ip' => '192.168.1.105', 'ago' => 7],
            ['user' => $drh, 'action' => 'IMPORT_AGENTS', 'cible' => 'agent:import', 'details' => ['fichier' => 'agents_mars2026.xlsx', 'importes' => 5, 'ignores' => 1], 'ip' => '192.168.1.115', 'ago' => 15],
            ['user' => $admin, 'action' => 'LOGIN', 'cible' => 'session', 'details' => ['ip' => '192.168.1.100'], 'ip' => '192.168.1.100', 'ago' => 0],
            ['user' => $dg, 'action' => 'LOGIN', 'cible' => 'session', 'details' => ['ip' => '192.168.1.110'], 'ip' => '192.168.1.110', 'ago' => 0],
            ['user' => $drh, 'action' => 'RECONCILE_PAYMENT', 'cible' => 'paiement:reconciliation', 'details' => ['operateur' => 'MTN', 'succes' => 12, 'echec' => 1], 'ip' => '192.168.1.115', 'ago' => 6],
            ['user' => $admin, 'action' => 'UPDATE_SETTINGS', 'cible' => 'settings:general', 'details' => ['champ' => 'app_name', 'ancien' => 'ANaTT v1', 'nouveau' => 'ANaTT Missions'], 'ip' => '192.168.1.100', 'ago' => 20],
            ['user' => $admin, 'action' => 'BACKUP_DATABASE', 'cible' => 'system:backup', 'details' => ['fichier' => 'backup_20260401_020000.sql.gz', 'taille' => '45.2 MB'], 'ip' => '192.168.1.100', 'ago' => 6],
        ];

        foreach ($logs as $log) {
            AuditLog::create([
                'user_id' => $log['user']?->id,
                'action' => $log['action'],
                'cible' => $log['cible'],
                'details' => $log['details'],
                'ip_address' => $log['ip'],
                'created_at' => now()->subDays($log['ago']),
                'updated_at' => now()->subDays($log['ago']),
            ]);
        }
    }
}
