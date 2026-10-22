<?php

use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\PermissionRepository;
use App\Support\Permissions;

/*
 * RBAC flexible : matrice rôle → permissions éditable à chaud.
 *
 * Vérifie la résolution (défaut config vs personnalisation base), la prise
 * d'effet immédiate, les garde-fous (clés réservées, séparation des tâches,
 * SUPER_ADMIN verrouillé) et l'autorisation (lecture ADMIN / écriture SUPER_ADMIN).
 */

beforeEach(function () {
    app(PermissionRepository::class)->vider();
});

// ── Résolution ──

it('résout la matrice par défaut depuis la config quand aucune personnalisation', function () {
    $defaut = config('profils.matrice.LECTEUR');

    expect(Permissions::pour('LECTEUR'))->toBe($defaut);
});

it('fait primer la personnalisation en base sur le défaut de config', function () {
    RolePermission::insert([
        ['role' => 'LECTEUR', 'permission' => 'dashboard.consulter', 'created_at' => now(), 'updated_at' => now()],
        ['role' => 'LECTEUR', 'permission' => 'audit.consulter', 'created_at' => now(), 'updated_at' => now()],
    ]);
    app(PermissionRepository::class)->vider();

    expect(Permissions::pour('LECTEUR'))
        ->toContain('audit.consulter')
        ->not->toContain('om.consulter'); // retiré par la personnalisation
});

it('donne toujours toutes les permissions au SUPER_ADMIN', function () {
    expect(Permissions::pour('SUPER_ADMIN'))->toBe(Permissions::TOUTES);
});

// ── Lecture (index) ──

it('expose la matrice à un administrateur (utilisateurs.gerer)', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/admin/roles')
        ->assertOk()
        ->assertJsonPath('data.matrice.LECTEUR', config('profils.matrice.LECTEUR'))
        ->assertJsonPath('data.non_assignables', PermissionRepository::NON_ASSIGNABLES);
});

it('refuse la lecture de la matrice à un profil sans utilisateurs.gerer', function () {
    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/admin/roles')
        ->assertForbidden();
});

// ── Écriture (update) ──

it('personnalise un profil et applique le changement immédiatement', function () {
    $lecteur = User::factory()->lecteur()->create();
    expect($lecteur->hasPermission('audit.consulter'))->toBeFalse();

    $reponse = $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/LECTEUR/permissions', [
            'permissions' => ['dashboard.consulter', 'om.consulter', 'rapports.consulter', 'audit.consulter'],
        ])
        ->assertOk();

    // Le contenu prime ; l'ordre du retour n'est pas garanti.
    expect($reponse->json('data.permissions'))
        ->toEqualCanonicalizing(['dashboard.consulter', 'om.consulter', 'rapports.consulter', 'audit.consulter']);

    // Nouvelle résolution : le LECTEUR a désormais accès à l'audit.
    expect($lecteur->fresh()->hasPermission('audit.consulter'))->toBeTrue();
});

it('journalise chaque édition de la matrice', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/LECTEUR/permissions', ['permissions' => ['dashboard.consulter']])
        ->assertOk();

    expect(AuditLog::where('action', 'ROLE_PERM_UPDATE')->where('entite', 'role_permissions')->exists())->toBeTrue();
});

it('réinitialise un profil au défaut de config', function () {
    RolePermission::insert([['role' => 'LECTEUR', 'permission' => 'audit.consulter', 'created_at' => now(), 'updated_at' => now()]]);
    app(PermissionRepository::class)->vider();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->deleteJson('/api/admin/roles/LECTEUR/permissions')
        ->assertOk()
        ->assertJsonPath('data.permissions', config('profils.matrice.LECTEUR'));

    expect(RolePermission::where('role', 'LECTEUR')->exists())->toBeFalse();
});

// ── Garde-fous ──

it('refuse d’attribuer une clé réservée au super administrateur', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/ADMIN/permissions', [
            'permissions' => ['dashboard.consulter', 'parametres.gerer'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('permissions');
});

it('refuse une matrice qui ferait d’un membre du profil un ordonnateur-payeur', function () {
    // Un contrôleur a reçu, à titre individuel, la confirmation des paiements.
    $controleur = User::factory()->controleur()->create(['name' => 'Paul AGBO']);
    UserPermission::create(['user_id' => $controleur->id, 'permission' => 'paiements.confirmer', 'granted' => true]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/CONTROLEUR/permissions', [
            'permissions' => ['om.consulter', 'om.valider', 'om.approuver'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('permissions');

    expect($controleur->fresh()->can('om.approuver'))->toBeFalse();
});

it('refuse une clé de permission inconnue', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/ADMIN/permissions', [
            'permissions' => ['dashboard.consulter', 'clé.bidon'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('permissions.1');
});

it('interdit qu’un même profil valide l’état et exécute les paiements', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/DG/permissions', [
            'permissions' => ['om.approuver', 'paiements.confirmer'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('permissions');
});

it('refuse de modifier la matrice du SUPER_ADMIN', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/SUPER_ADMIN/permissions', ['permissions' => ['dashboard.consulter']])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('role');
});

it('renvoie 404 pour un profil inconnu', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/roles/BIDON/permissions', ['permissions' => []])
        ->assertNotFound();
});

it('refuse l’écriture à un administrateur sans roles.gerer', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->putJson('/api/admin/roles/LECTEUR/permissions', ['permissions' => ['dashboard.consulter']])
        ->assertForbidden();
});
