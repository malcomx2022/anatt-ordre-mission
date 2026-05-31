<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            if ($user->role === null) {
                $user->role = User::ROLE_LECTEUR;
                $user->saveQuietly();
            }
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_ADMIN));
    }

    public function gestionnaireOm(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_GESTIONNAIRE_OM));
    }

    public function agentFinancier(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_AGENT_FINANCIER));
    }

    public function lecteur(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_LECTEUR));
    }
}
