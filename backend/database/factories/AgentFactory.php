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
        $operateur = fake()->randomElement(['MTN', 'MOOV']);
        // Préfixe cohérent : MTN -> 019X (90-99) ; MOOV -> 014X (40-49).
        $telephone = '01'.($operateur === 'MTN' ? '9' : '4').fake()->numerify('#######');

        return [
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'matricule' => 'ANT-'.fake()->unique()->numerify('#####'),
            'ifu' => fake()->numerify('############'),
            'grade' => fake()->randomElement(['Directeur', 'Chef de service', 'Cadre', 'Agent', 'Conducteur']),
            'indice' => fake()->numberBetween(250, 950),
            'salaire_brut' => fake()->numberBetween(120000, 450000),
            'categorie' => fake()->randomElement(['ORDRE_1', 'ORDRE_2', 'ORDRE_3']),
            'telephone' => $telephone,
            'operateur' => $operateur,
            'actif' => true,
        ];
    }

    public function mtn(): static
    {
        return $this->state(fn () => ['operateur' => 'MTN', 'telephone' => '019'.fake()->numerify('#######')]);
    }

    public function moov(): static
    {
        return $this->state(fn () => ['operateur' => 'MOOV', 'telephone' => '014'.fake()->numerify('#######')]);
    }

    public function categorie(string $categorie): static
    {
        return $this->state(fn () => ['categorie' => $categorie]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
