<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'om_valide',
            'titre' => 'OM 0055000167 validé — paiement préparable',
            'message' => 'Notification de test.',
            'data' => ['reference_semo' => '0055000167'],
            'lu_le' => null,
        ];
    }

    /** Notification déjà lue. */
    public function lue(): static
    {
        return $this->state(fn () => ['lu_le' => now()]);
    }
}
