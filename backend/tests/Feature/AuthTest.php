<?php

use App\Models\User;

it('authentifie un utilisateur avec identifiants valides', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('refuse un login avec mot de passe incorrect', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'mauvais',
    ]);

    $response->assertStatus(422);
});

it('refuse un login avec email inexistant', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'inexistant@test.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422);
});

it('retourne le profil utilisateur connecte', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->getJson('/api/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'ADMIN');
});

it('deconnecte un utilisateur', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Deconnexion reussie.');
});

it('refuse acces non authentifie a /auth/me', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertStatus(401);
});
