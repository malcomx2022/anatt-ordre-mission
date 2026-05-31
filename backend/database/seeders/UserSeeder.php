<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = [
            ['name' => 'Gislain AHOUANSOU', 'email' => 'admin@anatt.bj', 'role' => User::ROLE_ADMIN],
            ['name' => 'Marie DOSSOU', 'email' => 'gestion@anatt.bj', 'role' => User::ROLE_GESTIONNAIRE_OM],
            ['name' => 'Amina BELLO', 'email' => 'finance@anatt.bj', 'role' => User::ROLE_AGENT_FINANCIER],
            ['name' => 'Pierre HOUNGBO', 'email' => 'lecteur@anatt.bj', 'role' => User::ROLE_LECTEUR],
        ];

        foreach ($comptes as $compte) {
            $user = User::factory()->create([
                'name' => $compte['name'],
                'email' => $compte['email'],
            ]);
            $user->assignRole($compte['role']);
        }
    }
}
