<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrdreMission> */
class OrdreMissionFactory extends Factory
{
    protected $model = OrdreMission::class;

    public function definition(): array
    {
        $dateDepart = fake()->dateTimeBetween('now', '+1 month');
        $dateRetour = fake()->dateTimeBetween($dateDepart, '+2 months');
        $nbJours = (int) $dateDepart->diff($dateRetour)->days + 1;

        return [
            'numero' => 'OM-' . now()->year . '-' . fake()->unique()->numerify('####'),
            'agent_id' => Agent::factory(),
            'objet' => fake()->sentence(6),
            'destination' => fake()->randomElement(['Parakou', 'Natitingou', 'Abomey', 'Porto-Novo', 'Lokossa']),
            'date_depart' => $dateDepart,
            'date_retour' => $dateRetour,
            'nb_jours' => $nbJours,
            'taux_id' => TauxPerDiem::factory(),
            'montant_per_diem' => $nbJours * 25000,
            'statut' => 'BROUILLON',
        ];
    }

    public function soumis(): static
    {
        return $this->state(fn () => ['statut' => 'SOUMIS']);
    }

    public function valideN1(): static
    {
        return $this->state(fn () => ['statut' => 'VALIDE_N1']);
    }

    public function approuveDG(): static
    {
        return $this->state(fn () => ['statut' => 'APPROUVE_DG']);
    }

    public function rejete(): static
    {
        return $this->state(fn () => ['statut' => 'REJETE', 'commentaire_rejet' => 'Motif de rejet pour les tests']);
    }
}
