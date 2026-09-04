<?php

use App\Models\User;

/*
 * Sauvegardes de la base (BackupController) : listing dérivé, suppression avec
 * garde anti-traversée de répertoire (basename). La création (store) délègue à la
 * commande artisan db:backup et n'est pas rejouée ici. Le gating par permission
 * est couvert par RoleMiddlewareTest.
 */

beforeEach(function () {
    $this->su = User::factory()->superAdmin()->create();
    $this->dir = storage_path('app/backups');
    $this->nettoyer = function () {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir.'/*') as $f) {
                @unlink($f);
            }
        }
    };
    ($this->nettoyer)();
});

afterEach(function () {
    ($this->nettoyer)();
});

it('renvoie une liste vide quand aucune sauvegarde n’existe', function () {
    if (is_dir($this->dir)) {
        @rmdir($this->dir);
    }

    $this->actingAs($this->su)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('liste les sauvegardes avec leurs métadonnées dérivées', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/anatt_20260101_020000.sql', str_repeat('x', 2048));
    file_put_contents($this->dir.'/anatt_manuel.sql.gz', 'gz');
    file_put_contents($this->dir.'/ignore.txt', 'nope');

    $reponse = $this->actingAs($this->su)->getJson('/api/admin/backups')->assertOk();

    $noms = collect($reponse->json('data'))->pluck('name');
    expect($noms)->toContain('anatt_20260101_020000.sql')
        ->and($noms)->toContain('anatt_manuel.sql.gz')
        ->and($noms)->not->toContain('ignore.txt');

    // Le suffixe horaire _020000 marque une sauvegarde automatique.
    $auto = collect($reponse->json('data'))->firstWhere('name', 'anatt_20260101_020000.sql');
    expect($auto['type'])->toBe('Automatique')
        ->and($auto['status'])->toBe('Complet');
});

it('supprime une sauvegarde existante', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/a_supprimer.sql', 'data');

    $this->actingAs($this->su)->deleteJson('/api/admin/backups/a_supprimer.sql')
        ->assertOk()
        ->assertJsonPath('message', 'Sauvegarde supprimee.');

    expect(file_exists($this->dir.'/a_supprimer.sql'))->toBeFalse();
});

it('reste idempotent sur la suppression d’une sauvegarde absente', function () {
    @mkdir($this->dir, 0775, true);

    $this->actingAs($this->su)->deleteJson('/api/admin/backups/inexistante.sql')
        ->assertOk()
        ->assertJsonPath('message', 'Sauvegarde supprimee.');
});

it('refuse de supprimer un fichier qui n’est pas une sauvegarde', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/.env', 'SECRET=1');

    $this->actingAs($this->su)->deleteJson('/api/admin/backups/'.rawurlencode('.env'))
        ->assertStatus(422);

    // Le fichier hors périmètre n'est jamais touché.
    expect(file_exists($this->dir.'/.env'))->toBeTrue();
});
