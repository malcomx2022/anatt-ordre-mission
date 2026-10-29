<?php

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\PermissionRepository;
use App\Support\Permissions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Support\MysqlReel;

/*
 * db:backup puis db:restore avec les VRAIS binaires mysqldump / mysql
 * (DB_DUMP_BINARY / DB_RESTORE_BINARY) — défaut n° 10, jusqu'ici couvert par un
 * client « mysql » factice sous SQLite (BackupControllerTest).
 *
 * Chaque test avance l'horloge avant de restaurer : db:backup nomme ses fichiers
 * à la seconde, et la sauvegarde de sécurité prise par db:restore écraserait
 * sinon une sauvegarde de la même seconde.
 */

beforeEach(function () {
    MysqlReel::preparer($this);
    MysqlReel::viderSauvegardes();
});

afterEach(function () {
    MysqlReel::viderSauvegardes();
});

it('aller-retour réel : les données modifiées après la sauvegarde reviennent à leur état sauvegardé', function () {
    $su = User::factory()->superAdmin()->create();
    $agent = Agent::factory()->create(['nom' => 'ORIGINE', 'prenoms' => 'Sauvegarde']);

    expect(Artisan::call('db:backup'))->toBe(0);
    [$sauvegarde] = MysqlReel::sauvegardes();
    $dump = MysqlReel::lireSauvegarde($sauvegarde);
    expect($dump)->toContain('CREATE TABLE `agents`')->toContain('ORIGINE');

    // Après la sauvegarde : une modification, un ajout, une suppression.
    $agent->update(['nom' => 'MODIFIE']);
    $intrus = Agent::factory()->create(['nom' => 'INTRUS']);
    User::factory()->lecteur()->create(['email' => 'apres-sauvegarde@anatt.bj']);
    expect(Agent::count())->toBe(2);

    $this->travel(2)->seconds();

    $this->actingAs($su)->postJson("/api/admin/backups/{$sauvegarde}/restaurer", ['confirmation' => $sauvegarde])
        ->assertOk();

    // Données d'origine retrouvées, ajouts disparus.
    expect(Agent::count())->toBe(1)
        ->and(Agent::find($agent->id)->nom)->toBe('ORIGINE')
        ->and(Agent::find($intrus->id))->toBeNull()
        ->and(User::where('email', 'apres-sauvegarde@anatt.bj')->exists())->toBeFalse();

    // Sauvegarde de sécurité prise AVANT d'écraser : elle porte l'état d'avant la restauration.
    $securite = array_values(array_diff(MysqlReel::sauvegardes(), [$sauvegarde]));
    expect($securite)->toHaveCount(1)
        ->and($securite[0])->toEndWith('.sql.gz');
    expect(MysqlReel::lireSauvegarde($securite[0]))
        ->toContain('MODIFIE')
        ->toContain('INTRUS')
        ->toContain('apres-sauvegarde@anatt.bj');

    // Journalisée APRÈS la restauration (sinon écrasée avec le reste de la base).
    expect(AuditLog::where('action', 'BACKUP_RESTORE')->exists())->toBeTrue();
});

it('la sauvegarde de sécurité (.sql.gz) se restaure : une restauration malencontreuse se défait', function () {
    $agent = Agent::factory()->create(['nom' => 'ORIGINE']);
    Artisan::call('db:backup');
    [$sauvegarde] = MysqlReel::sauvegardes();

    $agent->update(['nom' => 'TRAVAIL DU JOUR']);
    $this->travel(2)->seconds();

    // Restauration malencontreuse : le travail du jour est écrasé…
    $this->artisan('db:restore', ['fichier' => $sauvegarde, '--force' => true])->assertSuccessful();
    expect($agent->fresh()->nom)->toBe('ORIGINE');

    // … mais la sauvegarde de sécurité compressée le rend (décompression PHP + client mysql).
    $securite = array_values(array_diff(MysqlReel::sauvegardes(), [$sauvegarde]))[0];
    $this->travel(2)->seconds();
    $this->artisan('db:restore', ['fichier' => $securite, '--force' => true, '--sans-sauvegarde' => true])
        ->assertSuccessful();

    expect($agent->fresh()->nom)->toBe('TRAVAIL DU JOUR');
});

it('db:restore purge la matrice des permissions mise en cache (n° 10)', function () {
    $repo = app(PermissionRepository::class);
    $defautLecteur = $repo->pour(Permissions::LECTEUR);
    expect(RolePermission::where('role', Permissions::LECTEUR)->exists())->toBeFalse();

    Artisan::call('db:backup');
    [$sauvegarde] = MysqlReel::sauvegardes();

    // Après la sauvegarde, le profil LECTEUR est personnalisé : la matrice
    // (cache sans expiration) mémorise le nouveau jeu de droits.
    $repo->remplacer(Permissions::LECTEUR, [Permissions::DASHBOARD_CONSULTER]);
    expect($repo->pour(Permissions::LECTEUR))->toBe([Permissions::DASHBOARD_CONSULTER])
        ->and(Cache::has('rbac.matrice'))->toBeTrue();

    $this->travel(2)->seconds();
    $this->artisan('db:restore', ['fichier' => $sauvegarde, '--force' => true])->assertSuccessful();

    // La table est revenue à l'état sauvegardé ET le cache ne la masque pas.
    expect(RolePermission::where('role', Permissions::LECTEUR)->exists())->toBeFalse()
        ->and($repo->pour(Permissions::LECTEUR))->toBe($defautLecteur);
});

it('un dump invalide fait échouer db:restore avec le message du client mysql, sans purger la matrice', function () {
    @mkdir(storage_path('app/backups'), 0775, true);
    file_put_contents(storage_path('app/backups/backup_2026-01-01_000000.sql'), "CECI N'EST PAS DU SQL;\n");

    $repo = app(PermissionRepository::class);
    $repo->matrice();
    expect(Cache::has('rbac.matrice'))->toBeTrue();

    $this->travel(2)->seconds();
    $this->artisan('db:restore', ['fichier' => 'backup_2026-01-01_000000.sql', '--force' => true])
        // Message unique « Échec de la restauration : … ERROR 1064 … » (erreur du client).
        ->expectsOutputToContain('ERROR 1064')
        ->assertFailed();

    expect(Cache::has('rbac.matrice'))->toBeTrue();
});
