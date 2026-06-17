<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();
});

it('admin liste et crée des comptes non super-admin', function () {
    $this->actingAs($this->admin)->getJson('/api/admin/users')->assertOk();

    $this->actingAs($this->admin)->postJson('/api/admin/users', [
        'name' => 'Nouveau', 'email' => 'n@anatt.bj', 'password' => 'MotDePasse123!', 'role' => User::ROLE_CONTROLEUR,
    ])->assertStatus(201)->assertJsonPath('data.role', 'CONTROLEUR');
});

it('admin ne peut pas créer un SUPER_ADMIN', function () {
    $this->actingAs($this->admin)->postJson('/api/admin/users', [
        'name' => 'X', 'email' => 'x@anatt.bj', 'password' => 'MotDePasse123!', 'role' => User::ROLE_SUPER_ADMIN,
    ])->assertStatus(403);
});

it('admin ne peut pas modifier un SUPER_ADMIN', function () {
    $cible = User::factory()->superAdmin()->create();

    $this->actingAs($this->admin)->putJson("/api/admin/users/{$cible->id}", ['name' => 'Renomme'])
        ->assertStatus(403);
});

it('super_admin peut créer un autre super_admin', function () {
    $this->actingAs($this->superAdmin)->postJson('/api/admin/users', [
        'name' => 'SU2', 'email' => 'su2@anatt.bj', 'password' => 'MotDePasse123!', 'role' => User::ROLE_SUPER_ADMIN,
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

it('le login renvoie les permissions et trace la dernière connexion', function () {
    $user = User::factory()->controleur()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'CONTROLEUR')
        ->assertJsonFragment(['om.valider']);

    expect($user->fresh()->derniere_connexion)->not->toBeNull();
});

it('expose les permissions du profil courant via /auth/me/permissions', function () {
    $controleur = User::factory()->controleur()->create();

    $reponse = $this->actingAs($controleur)->getJson('/api/auth/me/permissions')->assertOk();
    $perms = $reponse->json('data');

    expect($perms)->toContain('om.valider', 'audit.consulter')
        ->and($perms)->not->toContain('paiements.generer');

    // Le super admin a toutes les permissions.
    $perms = $this->actingAs($this->superAdmin)->getJson('/api/auth/me/permissions')->json('data');
    expect($perms)->toContain('superadmins.gerer', 'parametres.gerer');
});

it('active et désactive un compte via la route dédiée', function () {
    $cible = User::factory()->lecteur()->create(['actif' => true]);

    $this->actingAs($this->admin)->postJson("/api/admin/users/{$cible->id}/statut", ['actif' => false])
        ->assertOk()->assertJsonPath('data.actif', false);
    expect($cible->fresh()->actif)->toBeFalse();

    $this->actingAs($this->admin)->postJson("/api/admin/users/{$cible->id}/statut", ['actif' => true])
        ->assertOk()->assertJsonPath('data.actif', true);
});

it('interdit de se désactiver soi-même', function () {
    // Un second super admin pour que l'admin courant ne soit pas "le dernier".
    User::factory()->superAdmin()->create();

    $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->admin->id}/statut", ['actif' => false])
        ->assertStatus(422);
});

it('réinitialise le mot de passe et renvoie un mot de passe temporaire', function () {
    $cible = User::factory()->lecteur()->create();

    $reponse = $this->actingAs($this->admin)->postJson("/api/admin/users/{$cible->id}/reset-password")
        ->assertOk();
    $nouveau = $reponse->json('data.password');

    expect($nouveau)->toBeString()->and(strlen($nouveau))->toBeGreaterThanOrEqual(8);
    expect(Hash::check($nouveau, $cible->fresh()->password))->toBeTrue();
});

it('un admin ne peut pas réinitialiser le mot de passe d’un super_admin', function () {
    $cible = User::factory()->superAdmin()->create();

    $this->actingAs($this->admin)->postJson("/api/admin/users/{$cible->id}/reset-password")
        ->assertStatus(403);
});

it('journalise la création et le changement de profil', function () {
    $reponse = $this->actingAs($this->admin)->postJson('/api/admin/users', [
        'name' => 'Audité', 'email' => 'audit@anatt.bj', 'password' => 'MotDePasse123!', 'role' => User::ROLE_LECTEUR,
    ])->assertStatus(201);
    $id = $reponse->json('data.id');

    $this->actingAs($this->admin)->putJson("/api/admin/users/{$id}", ['role' => User::ROLE_CONTROLEUR])->assertOk();

    $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE_USER', 'entite' => 'user', 'entite_id' => $id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'CHANGE_ROLE_USER', 'entite' => 'user', 'entite_id' => $id]);
});

it('révoque les tokens actifs du compte lorsqu’il est désactivé', function () {
    $cible = User::factory()->controleur()->create(['actif' => true]);
    $cible->createToken('auth-token');
    expect($cible->tokens()->count())->toBe(1);

    $this->actingAs($this->admin)->postJson("/api/admin/users/{$cible->id}/statut", ['actif' => false])
        ->assertOk();

    expect($cible->fresh()->tokens()->count())->toBe(0);
});

it('révoque les tokens actifs lors d’un changement de rôle', function () {
    $cible = User::factory()->controleur()->create(['actif' => true]);
    $cible->createToken('auth-token');

    $this->actingAs($this->admin)->putJson("/api/admin/users/{$cible->id}", ['role' => User::ROLE_LECTEUR])
        ->assertOk();

    expect($cible->fresh()->tokens()->count())->toBe(0);
});

it('journalise distinctement la réinitialisation du mot de passe d’un super_admin', function () {
    $autreSuperAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($this->superAdmin)->postJson("/api/admin/users/{$autreSuperAdmin->id}/reset-password")
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'RESET_PASSWORD_SUPERADMIN', 'entite' => 'user', 'entite_id' => $autreSuperAdmin->id,
    ]);
});
