<?php

use App\Models\AuditLog;
use App\Models\User;

/*
 * Traçabilité des connexions : une connexion réussie laisse une entrée d'audit
 * (action LOGIN, entité session) ; un échec n'en laisse aucune.
 */

it('journalise une connexion réussie', function () {
    $user = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertOk();

    $log = AuditLog::where('action', 'LOGIN')->where('entite', 'session')->first();
    expect($log)->not->toBeNull()
        ->and((int) $log->user_id)->toBe($user->id)
        ->and($log->donnees['email'])->toBe($user->email);
});

it('ne journalise pas une connexion échouée', function () {
    $user = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mauvais'])
        ->assertStatus(422);

    expect(AuditLog::where('action', 'LOGIN')->count())->toBe(0);
});
