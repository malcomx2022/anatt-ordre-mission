<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agent> */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'matricule' => 'ANT-' . fake()->unique()->numerify('###'),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => '229' . fake()->numerify('########'),
            'numero_moov' => '229' . fake()->numerify('########'),
            'numero_mtn' => '229' . fake()->numerify('########'),
            'service' => fake()->randomElement(['Direction Technique', 'Direction Administrative', 'Comptabilite', 'Service Informatique']),
            'categorie' => fake()->randomElement(['A1', 'A2', 'B', 'C', 'D']),
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
