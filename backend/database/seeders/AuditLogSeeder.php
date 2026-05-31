<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'ADMIN')->first();
        $drh = User::where('role', 'DRH')->first();

        $logs = [
            ['user' => $admin, 'action' => 'LOGIN', 'entite' => 'session', 'entite_id' => null, 'donnees' => ['ip' => '192.168.1.100'], 'ip' => '192.168.1.100', 'ago' => 0],
            ['user' => $drh, 'action' => 'IMPORT_OM', 'entite' => 'ordre_mission', 'entite_id' => 1, 'donnees' => ['reference_semo' => '0541405636', 'agent' => 'DADA Richard', 'destination' => 'BOHICON'], 'ip' => '192.168.1.115', 'ago' => 2],
            ['user' => $drh, 'action' => 'IMPORT_OM', 'entite' => 'ordre_mission', 'entite_id' => 2, 'donnees' => ['reference_semo' => '0541405637', 'agent' => 'AKPACA Gérard', 'role' => 'CAV'], 'ip' => '192.168.1.115', 'ago' => 2],
            ['user' => $drh, 'action' => 'GENERATE_LOT', 'entite' => 'lot_paiement', 'entite_id' => 1, 'donnees' => ['mission' => 'BOHICON', 'tranche' => 'AVANCE', 'operateur' => 'MTN', 'nb_lignes' => 1, 'fichier' => 'MTN_BOHICON_AVANCE.txt'], 'ip' => '192.168.1.115', 'ago' => 1],
            ['user' => $drh, 'action' => 'CONFIRM_LOT', 'entite' => 'lot_paiement', 'entite_id' => 1, 'donnees' => ['tranche' => 'AVANCE', 'operateur' => 'MTN', 'statut' => 'GENERE -> CONFIRME'], 'ip' => '192.168.1.115', 'ago' => 1],
            ['user' => $admin, 'action' => 'UPDATE_BAREME', 'entite' => 'bareme', 'entite_id' => 1, 'donnees' => ['categorie' => 'ORDRE_1', 'zone' => 'INTERIEUR', 'type' => 'JOURNEE_COMPLETE'], 'ip' => '192.168.1.100', 'ago' => 10],
        ];

        foreach ($logs as $log) {
            DB::table('audit_logs')->insert([
                'user_id' => $log['user']?->id,
                'action' => $log['action'],
                'entite' => $log['entite'],
                'entite_id' => $log['entite_id'],
                'donnees' => json_encode($log['donnees'], JSON_UNESCAPED_UNICODE),
                'ip_address' => $log['ip'],
                'created_at' => now()->subDays($log['ago']),
            ]);
        }
    }
}
