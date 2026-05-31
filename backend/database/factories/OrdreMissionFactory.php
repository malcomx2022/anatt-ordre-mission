<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Mission;
use App\Models\OrdreMission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrdreMission> */
class OrdreMissionFactory extends Factory
{
    protected $model = OrdreMission::class;

    public function definition(): array
    {
        $total = 135000;
        $avance = (int) round($total / 3);

        return [
            'reference_semo' => fake()->unique()->numerify('##########'),
            'mission_id' => Mission::factory(),
            'agent_id' => Agent::factory(),
            'role' => 'PARTICIPANT',
            'categorie' => 'ORDRE_1',
            'zone_code' => 'INTERIEUR',
            'type_prise_en_charge' => 'JOURNEE_COMPLETE',
            'nb_jours' => 3,
            'montant_total' => $total,
            'avance' => $avance,
            'reliquat' => $total - $avance,
            'statut' => 'IMPORTE',
        ];
    }

    public function cav(): static
    {
        return $this->state(fn () => ['role' => 'CAV', 'categorie' => 'ORDRE_3']);
    }

    public function statut(string $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }
}
