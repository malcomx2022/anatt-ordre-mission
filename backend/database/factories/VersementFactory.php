<?php

namespace Database\Factories;

use App\Models\OrdreMission;
use App\Models\Versement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Versement> */
class VersementFactory extends Factory
{
    protected $model = Versement::class;

    public function definition(): array
    {
        return [
            'ordre_mission_id' => OrdreMission::factory(),
            'type' => 'AVANCE',
            'montant' => 94500,   // 70 % de 135000
            'operateur' => 'MTN',
            'telephone' => '0190000000',
            'statut' => 'EN_ATTENTE',
        ];
    }

    public function reliquat(): static
    {
        return $this->state(fn () => ['type' => 'RELIQUAT', 'montant' => 40500]);
    }

    public function moov(): static
    {
        return $this->state(fn () => ['operateur' => 'MOOV', 'telephone' => '0140000000']);
    }

    public function confirme(): static
    {
        return $this->state(fn () => ['statut' => 'CONFIRME', 'date_paiement' => now()]);
    }
}
