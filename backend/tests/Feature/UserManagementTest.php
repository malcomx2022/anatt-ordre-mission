<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();
});

it('admin liste et crée des comptes non super-admin', function () {
    $this->actingAs($this->admin)->getJson('/api/admin/users')->assertOk();

    $this->actingAs($this->admin)->postJson('/api/admin/users', [
        'name' => 'Nouveau', 'email' => 'n@anatt.bj', 'password' => 'secret123', 'role' => User::ROLE_CONTROLEUR,
    ])->assertStatus(201)->assertJsonPath('data.role', 'CONTROLEUR');
});

it('admin ne peut pas créer un SUPER_ADMIN', function () {
    $this->actingAs($this->admin)->postJson('/api/admin/users', [
        'name' => 'X', 'email' => 'x@anatt.bj', 'password' => 'secret123', 'role' => User::ROLE_SUPER_ADMIN,
    ])->assertStatus(403);
});

it('admin ne peut pas modifier un SUPER_ADMIN', function () {
    $cible = User::factory()->superAdmin()->create();

    $this->actingAs($this->admin)->putJson("/api/admin/users/{$cible->id}", ['name' => 'Renomme'])
        ->assertStatus(403);
});

it('super_admin peut créer un autre super_admin', function () {
    $this->actingAs($this->superAdmin)->postJson('/api/admin/users', [
        'name' => 'SU2', 'email' => 'su2@anatt.bj', 'password' => 'secret123', 'role' => User::ROLE_SUPER_ADMIN,
    ])->assertStatus(201)->assertJsonPath('data.role', 'SUPER_ADMIN');
});

it('interdit de désactiver le dernier super_admin actif', function () {
    // $this->superAdmin est le seul SUPER_ADMIN actif.
    $this->actingAs($this->superAdmin)->putJson("/api/admin/users/{$this->superAdmin->id}", ['actif' => false])
        ->assertStatus(422);
});

it('interdit de rétrograder le dernier super_admin actif', function () {
    $this->actingAs($this->superAdmin)->putJson("/api/admin/users/{$this->superAdmin->id}", ['role' => User::ROLE_ADMIN])
        ->assertStatus(422);
});

it('interdit de supprimer le dernier super_admin actif', function () {
    $autre = User::factory()->superAdmin()->create();
    // Il en reste 2 : on peut supprimer l'un...
    $this->actingAs($this->superAdmin)->deleteJson("/api/admin/users/{$autre->id}")->assertOk();
    // ...mais plus le dernier.
    $this->actingAs($this->superAdmin)->deleteJson("/api/admin/users/{$this->superAdmin->id}")->assertStatus(422);
});

it('autorise la rétrogradation s’il reste un autre super_admin actif', function () {
    $autre = User::factory()->superAdmin()->create();

    $this->actingAs($this->superAdmin)->putJson("/api/admin/users/{$autre->id}", ['role' => User::ROLE_ADMIN])
        ->assertOk()->assertJsonPath('data.role', 'ADMIN');
});

it('un compte désactivé ne peut pas se connecter', function () {
    $user = User::factory()->admin()->inactif()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertStatus(422);
});
