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
                $user->role = 'AGENT';
                $user->saveQuietly();
            }
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (\App\Models\User $user) => $user->assignRole('ADMIN'));
    }

    public function chefService(): static
    {
        return $this->afterCreating(fn (\App\Models\User $user) => $user->assignRole('CHEF_SERVICE'));
    }

    public function directeur(): static
    {
        return $this->afterCreating(fn (\App\Models\User $user) => $user->assignRole('DIRECTEUR'));
    }

    public function drh(): static
    {
        return $this->afterCreating(fn (\App\Models\User $user) => $user->assignRole('DRH'));
    }
}
