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
            'role' => 'AGENT',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'ADMIN']);
    }

    public function chefService(): static
    {
        return $this->state(fn () => ['role' => 'CHEF_SERVICE']);
    }

    public function directeur(): static
    {
        return $this->state(fn () => ['role' => 'DIRECTEUR']);
    }

    public function drh(): static
    {
        return $this->state(fn () => ['role' => 'DRH']);
    }
}
