<?php

use App\Models\Notification;
use App\Models\User;

/*
 * Feed in-app de la cloche : chaque utilisateur voit et modifie SEULEMENT ses
 * propres notifications. Endpoints : liste (+ compteur non-lues), marquer une
 * lue, tout marquer lu.
 */

beforeEach(function () {
    $this->user = User::factory()->gestionnaireOm()->create();
    $this->autre = User::factory()->agentFinancier()->create();
});

it('liste les notifications de l’utilisateur avec le compteur de non-lues', function () {
    Notification::factory()->count(2)->create(['user_id' => $this->user->id]);
    Notification::factory()->lue()->create(['user_id' => $this->user->id]);
    // Notification d'un autre utilisateur : ne doit pas fuiter.
    Notification::factory()->create(['user_id' => $this->autre->id]);

    $this->actingAs($this->user)->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.non_lues', 2)
        ->assertJsonStructure(['data' => [['id', 'type', 'titre', 'message', 'lu', 'created_at']], 'meta' => ['non_lues']]);
});

it('marque une notification comme lue', function () {
    $notif = Notification::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)->postJson("/api/notifications/{$notif->id}/lu")
        ->assertOk()
        ->assertJsonPath('data.lu', true);

    expect($notif->refresh()->lu_le)->not->toBeNull();
});

it('interdit de marquer lue la notification d’un autre utilisateur', function () {
    $notif = Notification::factory()->create(['user_id' => $this->autre->id]);

    $this->actingAs($this->user)->postJson("/api/notifications/{$notif->id}/lu")
        ->assertNotFound();

    expect($notif->refresh()->lu_le)->toBeNull();
});

it('marque toutes les notifications de l’utilisateur comme lues', function () {
    Notification::factory()->count(3)->create(['user_id' => $this->user->id]);
    $autrui = Notification::factory()->create(['user_id' => $this->autre->id]);

    $this->actingAs($this->user)->postJson('/api/notifications/lues')
        ->assertOk()
        ->assertJsonPath('data.non_lues', 0);

    expect(Notification::pour($this->user->id)->nonLues()->count())->toBe(0);
    // L'autre utilisateur n'est pas touché.
    expect($autrui->refresh()->lu_le)->toBeNull();
});

it('exige une authentification', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});
