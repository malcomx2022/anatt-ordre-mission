<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Paiement> */
class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    public function definition(): array
    {
        return [
            'ordre_mission_id' => OrdreMission::factory(),
            'agent_id' => Agent::factory(),
            'operateur' => fake()->randomElement(['MOOV', 'MTN']),
            'numero_destinataire' => '229' . fake()->numerify('########'),
            'montant' => fake()->randomElement([25000, 50000, 75000, 100000, 150000]),
            'statut' => 'EN_ATTENTE',
        ];
    }

    public function succes(): static
    {
        return $this->state(fn () => [
            'statut' => 'SUCCES',
            'reference_transaction' => 'TXN-' . fake()->bothify('??####'),
            'date_virement' => now(),
        ]);
    }

    public function echec(): static
    {
        return $this->state(fn () => ['statut' => 'ECHEC']);
    }
}
