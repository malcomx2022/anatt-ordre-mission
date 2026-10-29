<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PermissionRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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
        ->assertJsonPath('message', 'Sauvegarde supprimée.');

    expect(file_exists($this->dir.'/a_supprimer.sql'))->toBeFalse();
});

it('reste idempotent sur la suppression d’une sauvegarde absente', function () {
    @mkdir($this->dir, 0775, true);

    $this->actingAs($this->su)->deleteJson('/api/admin/backups/inexistante.sql')
        ->assertOk()
        ->assertJsonPath('message', 'Sauvegarde supprimée.');
});

it('refuse de supprimer un fichier qui n’est pas une sauvegarde', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/.env', 'SECRET=1');

    $this->actingAs($this->su)->deleteJson('/api/admin/backups/'.rawurlencode('.env'))
        ->assertStatus(422);

    // Le fichier hors périmètre n'est jamais touché.
    expect(file_exists($this->dir.'/.env'))->toBeTrue();
});

it('télécharge une sauvegarde existante, jamais un autre fichier', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/backup_2026-10-01_020000.sql', '-- dump');

    $this->actingAs($this->su)->get('/api/admin/backups/backup_2026-10-01_020000.sql/download')
        ->assertOk()
        ->assertDownload('backup_2026-10-01_020000.sql');

    $this->actingAs($this->su)->getJson('/api/admin/backups/.env/download')->assertStatus(422);
    $this->actingAs($this->su)->getJson('/api/admin/backups/absent.sql/download')->assertNotFound();
});

it('exige de recopier le nom du fichier avant de restaurer', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/backup_2026-10-01_020000.sql', '-- dump');
    Artisan::shouldReceive('call')->never();

    $this->actingAs($this->su)->postJson('/api/admin/backups/backup_2026-10-01_020000.sql/restaurer', ['confirmation' => 'oui'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('confirmation');
});

it('restaure via db:restore et journalise l’opération', function () {
    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/backup_2026-10-01_020000.sql', '-- dump');
    Artisan::shouldReceive('call')->once()
        ->with('db:restore', ['fichier' => 'backup_2026-10-01_020000.sql', '--force' => true])
        ->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('Base restaurée.');

    $this->actingAs($this->su)->postJson('/api/admin/backups/backup_2026-10-01_020000.sql/restaurer', [
        'confirmation' => 'backup_2026-10-01_020000.sql',
    ])->assertOk();

    expect(AuditLog::where('action', 'BACKUP_RESTORE')->exists())->toBeTrue();
});

it('refuse la restauration à un administrateur (réservée au super admin)', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/admin/backups/x.sql/restaurer', ['confirmation' => 'x.sql'])
        ->assertForbidden();
});

it('db:restore refuse un fichier absent sans toucher à la base', function () {
    $this->artisan('db:restore', ['fichier' => 'absent.sql', '--force' => true])->assertFailed();
});

it('db:restore purge la matrice des permissions mise en cache', function () {
    // Client « mysql » factice : consomme le dump et réussit. La matrice RBAC est
    // en cache sans expiration : sans purge, les droits d'avant la restauration
    // resteraient en vigueur alors que role_permissions vient d'être remplacée.
    $client = tempnam(sys_get_temp_dir(), 'anatt_mysql_');
    file_put_contents($client, "#!/bin/sh\ncat > /dev/null\n");
    chmod($client, 0755);
    config(['database.restore_binary' => $client]);

    @mkdir($this->dir, 0775, true);
    file_put_contents($this->dir.'/backup_2026-10-01_020000.sql', "SELECT 1;\n");

    app(PermissionRepository::class)->matrice();
    expect(Cache::has('rbac.matrice'))->toBeTrue();

    $this->artisan('db:restore', [
        'fichier' => 'backup_2026-10-01_020000.sql', '--force' => true, '--sans-sauvegarde' => true,
    ])->assertSuccessful();

    expect(Cache::has('rbac.matrice'))->toBeFalse();
    @unlink($client);
})->skip(PHP_OS_FAMILY === 'Windows', 'client factice en shell POSIX');

it('liste les sauvegardes de la plus récente à la plus ancienne', function () {
    if (! is_dir($this->dir)) {
        mkdir($this->dir, 0755, true);
    }
    // Comparées comme chaînes « d/m/Y », ces dates se classaient à l'envers.
    file_put_contents($this->dir.'/backup_octobre.sql', '-- dump');
    touch($this->dir.'/backup_octobre.sql', strtotime('2026-10-22 10:00'));
    file_put_contents($this->dir.'/backup_novembre.sql', '-- dump');
    touch($this->dir.'/backup_novembre.sql', strtotime('2026-11-03 10:00'));

    $noms = collect($this->actingAs($this->su)->getJson('/api/admin/backups')->assertOk()->json('data'))
        ->pluck('name')->all();

    expect($noms)->toBe(['backup_novembre.sql', 'backup_octobre.sql']);
});
