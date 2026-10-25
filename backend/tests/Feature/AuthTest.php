<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('répond 401 en JSON à une requête API non authentifiée, même sans en-tête Accept', function () {
    // Sans Accept: application/json, Laravel redirigeait vers une route « login »
    // inexistante et l'API renvoyait une page HTML 500.
    $this->get('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Non authentifie.');
});

it('répond en JSON aux autres erreurs HTTP de l’API, même sans en-tête Accept', function () {
    // DEPLOY-CPANEL.md §5.3 fait ouvrir /api/auth/login dans un navigateur : le
    // 405 revenait en page HTML, comme toute route /api/* inconnue en 404.
    $this->get('/api/auth/login')
        ->assertStatus(405)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);

    $this->get('/api/route-inexistante')
        ->assertNotFound()
        ->assertJsonPath('message', 'Route introuvable.');
});

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

it('journalise un changement de mot de passe (PWD_CHANGE)', function () {
    $user = User::factory()->admin()->create(['password' => bcrypt('AncienMotDePasse1!')]);

    $this->actingAs($user)->postJson('/api/auth/mot-de-passe/changer', [
        'current_password' => 'AncienMotDePasse1!',
        'password' => 'NouveauMotDePasse9@',
        'password_confirmation' => 'NouveauMotDePasse9@',
    ])->assertOk();

    $log = AuditLog::where('action', 'PWD_CHANGE')->where('entite', 'session')->first();
    expect($log)->not->toBeNull()
        ->and((int) $log->user_id)->toBe($user->id);
});

it('exige l’email pour réinitialiser le mot de passe', function () {
    $this->postJson('/api/auth/mot-de-passe/reset', [
        'token' => 'peu-importe',
        'password' => 'NouveauMotDePasse9@',
        'password_confirmation' => 'NouveauMotDePasse9@',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('réinitialise le mot de passe, lève le verrou, révoque les sessions et audite', function () {
    $user = User::factory()->admin()->create([
        'password' => bcrypt('AncienMotDePasse1!'),
        'must_change_password' => true,
    ]);
    // Une session existante doit être invalidée par la réinitialisation.
    $user->createToken('ancienne-session');
    expect($user->tokens()->count())->toBe(1);

    $token = Password::createToken($user);

    $this->postJson('/api/auth/mot-de-passe/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NouveauMotDePasse9@',
        'password_confirmation' => 'NouveauMotDePasse9@',
    ])->assertOk();

    $frais = $user->fresh();
    expect(Hash::check('NouveauMotDePasse9@', $frais->password))->toBeTrue()
        ->and($frais->must_change_password)->toBeFalse()
        ->and($frais->tokens()->count())->toBe(0);

    $log = AuditLog::where('action', 'PWD_RESET')->where('entite', 'session')->first();
    expect($log)->not->toBeNull()->and((int) $log->user_id)->toBe($user->id);
});

it('génère un lien de réinitialisation pointant vers la SPA avec l’email', function () {
    Notification::fake();
    $user = User::factory()->admin()->create();

    $this->postJson('/api/auth/mot-de-passe/email', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '/reinitialiser/')
            && str_contains($url, 'email='.urlencode($user->email));
    });
});
