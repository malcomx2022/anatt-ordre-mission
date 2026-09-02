<?php

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\PermissionRepository;

/*
 * RBAC flexible — niveau 3 : rôles personnalisés.
 *
 * Création/renommage/suppression de profils au-delà des 8 système, intégration
 * dans la matrice et l'affectation des utilisateurs, garde-fous et autorisation.
 */

beforeEach(function () {
    app(PermissionRepository::class)->vider();
});

// ── Cycle de vie ──

it('crée un rôle personnalisé avec des permissions initiales', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson('/api/admin/roles', [
            'code' => 'CAISSIER_REGIONAL',
            'libelle' => 'Caissier régional',
            'permissions' => ['dashboard.consulter', 'paiements.consulter'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'CAISSIER_REGIONAL');

    expect(Role::where('code', 'CAISSIER_REGIONAL')->exists())->toBeTrue();

    app(PermissionRepository::class)->vider();
    expect(app(PermissionRepository::class)->pour('CAISSIER_REGIONAL'))
        ->toEqualCanonicalizing(['dashboard.consulter', 'paiements.consulter']);
});

it('intègre le rôle personnalisé à la liste et à la matrice', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);
    RolePermission::create(['role' => 'AUDITEUR_EXTERNE', 'permission' => 'audit.consulter']);
    app(PermissionRepository::class)->vider();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/admin/roles')
        ->assertOk()
        ->assertJsonPath('data.matrice.AUDITEUR_EXTERNE', ['audit.consulter']);

    expect(app(PermissionRepository::class)->codes())->toContain('AUDITEUR_EXTERNE');
});

it('applique les permissions du rôle personnalisé à un utilisateur', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);
    RolePermission::create(['role' => 'AUDITEUR_EXTERNE', 'permission' => 'audit.consulter']);
    app(PermissionRepository::class)->vider();

    $user = User::factory()->create(['role' => 'AUDITEUR_EXTERNE']);

    expect($user->hasPermission('audit.consulter'))->toBeTrue()
        ->and($user->hasPermission('paiements.generer'))->toBeFalse();
});

it('édite les permissions d’un rôle personnalisé via la matrice', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);
    app(PermissionRepository::class)->vider();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/AUDITEUR_EXTERNE/permissions', [
            'permissions' => ['dashboard.consulter', 'rapports.consulter'],
        ])
        ->assertOk();

    app(PermissionRepository::class)->vider();
    expect(app(PermissionRepository::class)->pour('AUDITEUR_EXTERNE'))
        ->toEqualCanonicalizing(['dashboard.consulter', 'rapports.consulter']);
});

it('renomme un rôle personnalisé', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/AUDITEUR_EXTERNE', ['libelle' => 'Auditeur indépendant'])
        ->assertOk()
        ->assertJsonPath('data.libelle', 'Auditeur indépendant');
});

it('supprime un rôle personnalisé sans utilisateur', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);
    RolePermission::create(['role' => 'AUDITEUR_EXTERNE', 'permission' => 'audit.consulter']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->deleteJson('/api/admin/roles/AUDITEUR_EXTERNE')
        ->assertOk();

    expect(Role::where('code', 'AUDITEUR_EXTERNE')->exists())->toBeFalse()
        ->and(RolePermission::where('role', 'AUDITEUR_EXTERNE')->exists())->toBeFalse();
});

it('permet d’affecter un utilisateur à un rôle personnalisé via l’API', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);
    app(PermissionRepository::class)->vider();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/admin/users', [
            'name' => 'Jean Audit',
            'email' => 'jean.audit@anatt.bj',
            'password' => 'MotDePasse#2026',
            'role' => 'AUDITEUR_EXTERNE',
        ])
        ->assertCreated();

    expect(User::where('email', 'jean.audit@anatt.bj')->first()->role)->toBe('AUDITEUR_EXTERNE');
});

// ── Garde-fous ──

it('refuse un code de rôle en collision avec un profil système', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson('/api/admin/roles', ['code' => 'ADMIN', 'libelle' => 'Bis'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('code');
});

it('refuse un code au mauvais format', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson('/api/admin/roles', ['code' => 'petit-role', 'libelle' => 'X'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('code');
});

it('refuse un code personnalisé en doublon', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson('/api/admin/roles', ['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Autre'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('code');
});

it('refuse de renommer un profil système', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/ADMIN', ['libelle' => 'Chef'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('role');
});

it('refuse de supprimer un profil système', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->deleteJson('/api/admin/roles/LECTEUR')
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('role');
});

it('refuse de supprimer un rôle encore porté par un utilisateur', function () {
    Role::create(['code' => 'AUDITEUR_EXTERNE', 'libelle' => 'Auditeur externe', 'systeme' => false]);
    app(PermissionRepository::class)->vider();
    User::factory()->create(['role' => 'AUDITEUR_EXTERNE']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->deleteJson('/api/admin/roles/AUDITEUR_EXTERNE')
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('role');
});

it('applique la séparation ordonnateur/payeur à un rôle personnalisé', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson('/api/admin/roles', [
            'code' => 'ROLE_CUMUL',
            'libelle' => 'Cumul interdit',
            'permissions' => ['om.approuver', 'paiements.confirmer'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('permissions');
});

it('refuse la création d’un rôle à un administrateur sans roles.gerer', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/admin/roles', ['code' => 'ROLE_X', 'libelle' => 'X'])
        ->assertForbidden();
});
