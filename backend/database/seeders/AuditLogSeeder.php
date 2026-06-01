<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Entrées d'audit « générales » pour le journal d'activité (connexions, barème).
 *
 * L'historique des OM (IMPORT_OM / VALIDATE_OM) et des lots (GENERATE_LOT /
 * CONFIRM_LOT) est désormais journalisé par OrdreMissionSeeder et
 * PaiementDemoSeeder au plus près des données réelles : ce seeder ne les duplique pas.
 */
class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'ADMIN')->first();
        $gestion = User::where('role', 'GESTIONNAIRE_OM')->first();
        $controle = User::where('role', 'CONTROLEUR')->first();

        $logs = [
            ['user' => $admin, 'action' => 'LOGIN', 'entite' => 'session', 'entite_id' => null, 'donnees' => ['ip' => '192.168.1.100'], 'ip' => '192.168.1.100', 'ago' => 0],
            ['user' => $gestion, 'action' => 'LOGIN', 'entite' => 'session', 'entite_id' => null, 'donnees' => ['ip' => '192.168.1.115'], 'ip' => '192.168.1.115', 'ago' => 0],
            ['user' => $controle, 'action' => 'LOGIN', 'entite' => 'session', 'entite_id' => null, 'donnees' => ['ip' => '192.168.1.120'], 'ip' => '192.168.1.120', 'ago' => 1],
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
