<?php

use App\Models\User;

it('authentifie un utilisateur avec identifiants valides', function () {
    $user = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user']])
        ->assertJsonPath('data.user.role', 'ADMIN');
});

it('refuse un login avec mot de passe incorrect', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'mauvais',
    ])->assertStatus(422);
});

it('refuse un login avec email inexistant', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'inexistant@test.com',
        'password' => 'password',
    ])->assertStatus(422);
});

it('retourne le profil utilisateur connecte', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'ADMIN');
});

it('deconnecte un utilisateur', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Deconnexion reussie.');
});

it('refuse acces non authentifie a /auth/me', function () {
    $this->getJson('/api/auth/me')->assertStatus(401);
});
