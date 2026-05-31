<?php

namespace Database\Factories;

use App\Models\Mission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mission> */
class MissionFactory extends Factory
{
    protected $model = Mission::class;

    public function definition(): array
    {
        return [
            'objet' => fake()->sentence(4),
            'destination' => fake()->randomElement(['Bohicon', 'Parakou', 'Natitingou', 'Abomey', 'Lokossa']),
            'zone_code' => 'INTERIEUR',
            'date_depart' => '2026-06-01',
            'date_retour' => '2026-06-03',
            'imputation_budgetaire' => 'ANATT-'.fake()->numerify('####'),
            'prise_en_charge_tiers' => false,
        ];
    }

    public function exterieur(string $zone = 'EXT_ZONE_I'): static
    {
        return $this->state(fn () => ['zone_code' => $zone, 'destination' => 'Paris']);
    }

    public function exonere(): static
    {
        return $this->state(fn () => ['prise_en_charge_tiers' => true]);
    }
}
