<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Compte SUPER_ADMIN initial (créé à l'installation, paramétrable via .env).
        $sa = config('profils.superadmin');
        User::updateOrCreate(
            ['email' => $sa['email']],
            ['name' => $sa['name'], 'password' => Hash::make($this->motDePasseSuperAdmin($sa)), 'role' => User::ROLE_SUPER_ADMIN, 'actif' => true],
        );

        // Comptes de démonstration (mot de passe 'password') : JAMAIS en production.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Un compte de démonstration par profil.
        $comptes = [
            ['name' => 'Gislain AHOUANSOU', 'email' => 'admin@anatt.bj', 'role' => User::ROLE_ADMIN],
            ['name' => 'Marie DOSSOU', 'email' => 'gestion@anatt.bj', 'role' => User::ROLE_GESTIONNAIRE_OM],
            ['name' => 'Amina BELLO', 'email' => 'finance@anatt.bj', 'role' => User::ROLE_AGENT_FINANCIER],
            ['name' => 'Paul AGBO', 'email' => 'controle@anatt.bj', 'role' => User::ROLE_CONTROLEUR],
            ['name' => 'Pierre HOUNGBO', 'email' => 'lecteur@anatt.bj', 'role' => User::ROLE_LECTEUR],
        ];

        foreach ($comptes as $c) {
            // Ne jamais écraser le super admin si un email de démo coïncide avec le sien.
            if ($c['email'] === $sa['email']) {
                continue;
            }
            User::updateOrCreate(
                ['email' => $c['email']],
                ['name' => $c['name'], 'password' => Hash::make('password'), 'role' => $c['role'], 'actif' => true],
            );
        }
    }

    /**
     * Mot de passe du super admin initial.
     * - Défini via SUPERADMIN_PASSWORD : utilisé tel quel.
     * - Absent en local/testing : repli de développement 'password'.
     * - Absent en production : ERREUR (aucun mot de passe par défaut en prod).
     *
     * @param  array{name:string,email:string,password:?string}  $sa
     */
    private function motDePasseSuperAdmin(array $sa): string
    {
        if (! empty($sa['password'])) {
            return $sa['password'];
        }

        if (app()->environment(['local', 'testing'])) {
            return 'password';
        }

        throw new \RuntimeException(
            'SUPERADMIN_PASSWORD doit être défini dans le .env en production '
            .'(aucun mot de passe par défaut autorisé).'
        );
    }
}
