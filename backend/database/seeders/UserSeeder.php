<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Gislain AHOUANSOU',
            'email' => 'admin@anatt.bj',
            'role' => 'ADMIN',
        ]);

        User::factory()->create([
            'name' => 'Marie DOSSOU',
            'email' => 'chef@anatt.bj',
            'role' => 'CHEF_SERVICE',
        ]);

        User::factory()->create([
            'name' => 'Pierre HOUNGBO',
            'email' => 'directeur@anatt.bj',
            'role' => 'DIRECTEUR',
        ]);

        User::factory()->create([
            'name' => 'Amina BELLO',
            'email' => 'drh@anatt.bj',
            'role' => 'DRH',
        ]);

        User::factory()->create([
            'name' => 'Jean KOUASSI',
            'email' => 'agent@anatt.bj',
            'role' => 'AGENT',
        ]);
    }
}
