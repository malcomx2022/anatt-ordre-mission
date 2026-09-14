<?php

namespace Database\Factories;

use App\Models\Bareme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bareme> */
class BaremeFactory extends Factory
{
    protected $model = Bareme::class;

    public function definition(): array
    {
        return [
            'categorie' => 'INDICE_SUP_800',
            'zone_code' => 'INTERIEUR',
            'type_prise_en_charge' => 'JOURNEE_COMPLETE',
            'taux_journalier' => 45000,
        ];
    }
}
