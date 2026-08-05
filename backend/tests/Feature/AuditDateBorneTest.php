<?php

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;

/*
 * Borne « date_fin » du journal : created_at est un TIMESTAMP, donc un filtre
 * en date seule doit couvrir la journée entière (jusqu'à 23:59:59), sinon les
 * événements du dernier jour de la plage disparaissent.
 */

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('inclut un événement en journée le dernier jour de la plage', function () {
    AuditLog::create([
        'action' => 'LOGIN', 'entite' => 'session',
        'created_at' => Carbon::parse('2026-01-31 14:00:00'),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/audit?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('exclut un événement postérieur à la borne de fin', function () {
    AuditLog::create([
        'action' => 'LOGIN', 'entite' => 'session',
        'created_at' => Carbon::parse('2026-02-01 00:01:00'),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/audit?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
