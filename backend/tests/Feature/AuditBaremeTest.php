<?php

use App\Models\AuditLog;
use App\Models\Bareme;
use App\Models\User;

/*
 * Traçabilité des barèmes : créer / modifier / supprimer un barème (action
 * sensible, permission baremes.modifier) doit laisser une entrée d'audit.
 */

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('journalise la création d’un barème', function () {
    $this->actingAs($this->admin)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_1', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ])->assertStatus(201);

    $log = AuditLog::where('action', 'CREATE_BAREME')->where('entite', 'bareme')->first();
    expect($log)->not->toBeNull()
        ->and((int) $log->donnees['taux_journalier'])->toBe(45000)
        ->and((int) $log->user_id)->toBe($this->admin->id);
});

it('journalise la modification d’un barème (ancien → nouveau taux)', function () {
    $bareme = Bareme::factory()->create(['taux_journalier' => 45000]);

    $this->actingAs($this->admin)->putJson("/api/baremes/{$bareme->id}", ['taux_journalier' => 50000])
        ->assertOk();

    $log = AuditLog::where('action', 'UPDATE_BAREME')->where('entite_id', $bareme->id)->first();
    expect($log)->not->toBeNull()
        ->and((int) $log->donnees['ancien_taux'])->toBe(45000)
        ->and((int) $log->donnees['nouveau_taux'])->toBe(50000);
});

it('journalise la suppression d’un barème', function () {
    $bareme = Bareme::factory()->create();

    $this->actingAs($this->admin)->deleteJson("/api/baremes/{$bareme->id}")->assertOk();

    expect(AuditLog::where('action', 'DELETE_BAREME')->where('entite', 'bareme')
        ->where('entite_id', $bareme->id)->count())->toBe(1);
});
