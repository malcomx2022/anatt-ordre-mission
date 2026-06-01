<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Compte SUPER_ADMIN initial (créé à l'installation).
        User::updateOrCreate(
            ['email' => 'superadmin@anatt.bj'],
            ['name' => 'Super Administrateur', 'password' => Hash::make('password'), 'role' => User::ROLE_SUPER_ADMIN, 'actif' => true],
        );

        // Un compte de démonstration par profil.
        $comptes = [
            ['name' => 'Gislain AHOUANSOU', 'email' => 'admin@anatt.bj', 'role' => User::ROLE_ADMIN],
            ['name' => 'Marie DOSSOU', 'email' => 'gestion@anatt.bj', 'role' => User::ROLE_GESTIONNAIRE_OM],
            ['name' => 'Amina BELLO', 'email' => 'finance@anatt.bj', 'role' => User::ROLE_AGENT_FINANCIER],
            ['name' => 'Paul AGBO', 'email' => 'controle@anatt.bj', 'role' => User::ROLE_CONTROLEUR],
            ['name' => 'Pierre HOUNGBO', 'email' => 'lecteur@anatt.bj', 'role' => User::ROLE_LECTEUR],
        ];

        foreach ($comptes as $c) {
            User::updateOrCreate(
                ['email' => $c['email']],
                ['name' => $c['name'], 'password' => Hash::make('password'), 'role' => $c['role'], 'actif' => true],
            );
        }
    }
}
