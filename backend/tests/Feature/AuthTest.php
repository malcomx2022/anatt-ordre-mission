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

it('bloque l’API et impose le changement de mot de passe quand must_change_password est levé', function () {
    $user = User::factory()->admin()->create(['must_change_password' => true]);

    // Une route métier est verrouillée (423) tant que le mot de passe n'est pas changé.
    $this->actingAs($user)->getJson('/api/agents')
        ->assertStatus(423)
        ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    // L'échappatoire reste accessible : /auth/me et le changement de mot de passe.
    $this->actingAs($user)->getJson('/api/auth/me')->assertOk();
});

it('permet à l’utilisateur de changer son mot de passe et lève le verrou', function () {
    $user = User::factory()->admin()->create([
        'password' => bcrypt('AncienMotDePasse1!'),
        'must_change_password' => true,
    ]);

    $this->actingAs($user)->postJson('/api/auth/mot-de-passe/changer', [
        'current_password' => 'AncienMotDePasse1!',
        'password' => 'NouveauMotDePasse9@',
        'password_confirmation' => 'NouveauMotDePasse9@',
    ])->assertOk();

    expect($user->fresh()->must_change_password)->toBeFalse();

    // L'API redevient accessible.
    $this->actingAs($user->fresh())->getJson('/api/agents')->assertOk();
});

it('refuse le changement si le mot de passe actuel est incorrect', function () {
    $user = User::factory()->admin()->create([
        'password' => bcrypt('AncienMotDePasse1!'),
        'must_change_password' => true,
    ]);

    $this->actingAs($user)->postJson('/api/auth/mot-de-passe/changer', [
        'current_password' => 'FAUX',
        'password' => 'NouveauMotDePasse9@',
        'password_confirmation' => 'NouveauMotDePasse9@',
    ])->assertStatus(422);

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('un compte réinitialisé par un admin doit changer son mot de passe (must_change_password)', function () {
    $admin = User::factory()->admin()->create();
    $cible = User::factory()->controleur()->create(['must_change_password' => false]);

    $this->actingAs($admin)->postJson("/api/admin/users/{$cible->id}/reset-password")
        ->assertOk();

    expect($cible->fresh()->must_change_password)->toBeTrue();
});
