<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\UserPermissionService;
use App\Support\Permissions;

/*
 * RBAC flexible — niveau 2 : overrides de permissions par utilisateur.
 *
 * Vérifie la résolution (base ∪ accordées − retirées), la prise d'effet
 * immédiate, les garde-fous et l'autorisation (lecture ADMIN / écriture SUPER_ADMIN).
 */

// ── Résolution ──

it('résout les permissions de base du profil quand aucun override', function () {
    $lecteur = User::factory()->lecteur()->create();

    expect($lecteur->permissions())->toBe(Permissions::pour('LECTEUR'));
});

it('accorde une permission supplémentaire à un utilisateur', function () {
    $lecteur = User::factory()->lecteur()->create();
    UserPermission::create(['user_id' => $lecteur->id, 'permission' => 'audit.consulter', 'granted' => true]);

    expect($lecteur->fresh()->hasPermission('audit.consulter'))->toBeTrue();
});

it('retire une permission que le profil donnerait', function () {
    $lecteur = User::factory()->lecteur()->create();
    expect($lecteur->hasPermission('om.consulter'))->toBeTrue();

    UserPermission::create(['user_id' => $lecteur->id, 'permission' => 'om.consulter', 'granted' => false]);

    expect($lecteur->fresh()->hasPermission('om.consulter'))->toBeFalse();
});

it('donne toujours toutes les permissions au SUPER_ADMIN, overrides ignorés', function () {
    $sa = User::factory()->superAdmin()->create();
    UserPermission::create(['user_id' => $sa->id, 'permission' => 'om.consulter', 'granted' => false]);

    expect($sa->fresh()->permissions())->toBe(Permissions::TOUTES);
});

// ── Lecture (GET) ──

it('expose base, accords, retraits et effectif à un administrateur', function () {
    $lecteur = User::factory()->lecteur()->create();
    UserPermission::create(['user_id' => $lecteur->id, 'permission' => 'audit.consulter', 'granted' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson("/api/admin/users/{$lecteur->id}/permissions")
        ->assertOk()
        ->assertJsonPath('data.role', 'LECTEUR')
        ->assertJsonPath('data.granted', ['audit.consulter'])
        ->assertJsonPath('data.base', config('profils.matrice.LECTEUR'));
});

it('refuse la lecture des overrides sans utilisateurs.gerer', function () {
    $cible = User::factory()->lecteur()->create();

    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson("/api/admin/users/{$cible->id}/permissions")
        ->assertForbidden();
});

// ── Écriture (PUT) ──

it('applique les overrides immédiatement', function () {
    $lecteur = User::factory()->lecteur()->create();

    $reponse = $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$lecteur->id}/permissions", [
            'granted' => ['audit.consulter'],
            'revoked' => ['om.consulter'],
        ])
        ->assertOk();

    expect($reponse->json('data.effectives'))
        ->toContain('audit.consulter')
        ->not->toContain('om.consulter');

    $frais = $lecteur->fresh();
    expect($frais->hasPermission('audit.consulter'))->toBeTrue()
        ->and($frais->hasPermission('om.consulter'))->toBeFalse();
});

it('journalise chaque édition des overrides', function () {
    $cible = User::factory()->lecteur()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", ['granted' => ['audit.consulter'], 'revoked' => []])
        ->assertOk();

    expect(AuditLog::where('action', 'USER_PERM_UPDATE')->where('entite_id', $cible->id)->exists())->toBeTrue();
});

it('remplace intégralement les overrides existants', function () {
    $cible = User::factory()->lecteur()->create();
    UserPermission::create(['user_id' => $cible->id, 'permission' => 'audit.consulter', 'granted' => true]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", ['granted' => ['personnel.consulter'], 'revoked' => []])
        ->assertOk();

    expect($cible->fresh()->hasPermission('audit.consulter'))->toBeFalse()
        ->and($cible->fresh()->hasPermission('personnel.consulter'))->toBeTrue();
});

// ── Garde-fous ──

it('refuse d’accorder une clé réservée au super administrateur', function () {
    $cible = User::factory()->admin()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", ['granted' => ['parametres.gerer'], 'revoked' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('granted');
});

it('refuse une clé accordée ET retirée', function () {
    $cible = User::factory()->lecteur()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", [
            'granted' => ['audit.consulter'],
            'revoked' => ['audit.consulter'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('granted');
});

it('interdit qu’un utilisateur valide l’état et exécute les paiements', function () {
    // Le DG possède om.approuver en base ; lui accorder paiements.confirmer cumulerait les rôles.
    $dg = User::factory()->dg()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$dg->id}/permissions", ['granted' => ['paiements.confirmer'], 'revoked' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('granted');
});

it('refuse de poser des overrides sur un SUPER_ADMIN', function () {
    $cible = User::factory()->superAdmin()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", ['granted' => ['om.consulter'], 'revoked' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('user');
});

it('refuse une clé de permission inconnue', function () {
    $cible = User::factory()->lecteur()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", ['granted' => ['clé.bidon'], 'revoked' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('granted.0');
});

it('refuse l’écriture des overrides à un administrateur sans roles.gerer', function () {
    $cible = User::factory()->lecteur()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->putJson("/api/admin/users/{$cible->id}/permissions", ['granted' => ['audit.consulter'], 'revoked' => []])
        ->assertForbidden();
});

// ── Changement de rôle × overrides : séparation ordonnateur/payeur ──

it('refuse un changement de rôle qui, combiné à un override, créerait un cumul interdit', function () {
    // LECTEUR (pas d'om.approuver) à qui on a accordé paiements.confirmer : légal en l'état.
    $u = User::factory()->lecteur()->create();
    UserPermission::create(['user_id' => $u->id, 'permission' => 'paiements.confirmer', 'granted' => true]);

    // Le passer DG (porte om.approuver) recréerait le cumul om.approuver + paiements.confirmer.
    $this->actingAs(User::factory()->admin()->create())
        ->putJson("/api/admin/users/{$u->id}", ['role' => 'DG'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('role');

    expect($u->fresh()->role)->toBe('LECTEUR'); // rôle inchangé
});

it('autorise un changement de rôle sans cumul malgré un override', function () {
    $u = User::factory()->lecteur()->create();
    UserPermission::create(['user_id' => $u->id, 'permission' => 'audit.consulter', 'granted' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->putJson("/api/admin/users/{$u->id}", ['role' => 'DG'])
        ->assertOk();

    expect($u->fresh()->role)->toBe('DG');
});

// ── Service ──

it('combine base, accords et retraits sans doublon', function () {
    $effectives = UserPermissionService::combiner(
        ['a', 'b', 'c'],
        ['c', 'd'],
        ['b'],
    );

    expect($effectives)->toEqualCanonicalizing(['a', 'c', 'd']);
});
