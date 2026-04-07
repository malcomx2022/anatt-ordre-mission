<?php

namespace Database\Factories;

use App\Models\TauxPerDiem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TauxPerDiem> */
class TauxPerDiemFactory extends Factory
{
    protected $model = TauxPerDiem::class;

    public function definition(): array
    {
        return [
            'categorie' => fake()->randomElement(['A1', 'A2', 'B', 'C', 'D']),
            'destination_type' => fake()->randomElement(['LOCALE', 'REGIONALE', 'INTERNATIONALE']),
            'montant_journalier' => fake()->randomElement([10000, 15000, 25000, 50000, 100000]),
            'annee' => now()->year,
            'actif' => true,
        ];
    }
}
