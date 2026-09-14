<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Support\Parametres;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrdreMission> */
class OrdreMissionFactory extends Factory
{
    protected $model = OrdreMission::class;

    public function definition(): array
    {
        $total = 135000;
        $avance = (int) round($total * Parametres::partAvance() / 100);   // part paramétrable (70 % par défaut)

        return [
            'reference_semo' => fake()->unique()->numerify('##########'),
            'mission_id' => Mission::factory(),
            'agent_id' => Agent::factory(),
            'role' => 'PARTICIPANT',
            'categorie' => 'INDICE_SUP_800',
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
        return $this->state(fn () => ['role' => 'CAV', 'categorie' => 'INDICE_INF_400']);
    }

    public function statut(string $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }
}
