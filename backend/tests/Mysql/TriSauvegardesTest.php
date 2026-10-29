<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\MysqlReel;

/*
 * Tri des sauvegardes (GET /api/admin/backups, défaut n° 14) sur de VRAIS fichiers
 * produits par db:backup (mysqldump), et non sur des fichiers écrits à la main.
 */

beforeEach(function () {
    MysqlReel::preparer($this);
    MysqlReel::viderSauvegardes();
    $this->su = User::factory()->superAdmin()->create();
});

afterEach(function () {
    MysqlReel::viderSauvegardes();
});

/** Noms renvoyés par la liste de l'écran Sauvegardes, dans l'ordre affiché. */
function nomsListes(object $t): array
{
    return collect($t->actingAs($t->su)->getJson('/api/admin/backups')->assertOk()->json('data'))
        ->pluck('name')->all();
}

it('liste les sauvegardes successives de db:backup, la plus récente d’abord', function () {
    // Horodatages réels distincts (nom à la seconde, filemtime à la seconde).
    $produites = [];
    foreach ([1, 2, 3] as $i) {
        if ($i > 1) {
            sleep(1);
        }
        expect(Artisan::call('db:backup', $i === 2 ? ['--compress' => true] : []))->toBe(0);
        $produites[] = array_values(array_diff(MysqlReel::sauvegardes(), $produites))[0];
    }

    expect($produites[1])->toEndWith('.sql.gz');
    foreach ($produites as $nom) {
        expect(MysqlReel::lireSauvegarde($nom))->toContain('CREATE TABLE `ordre_missions`');
    }

    expect(nomsListes($this))->toBe(array_reverse($produites));
});

it('trie sur l’horodatage du fichier, ni sur le nom ni sur la date affichée « d/m/Y » (n° 14)', function () {
    // Trois vraies sauvegardes, aux noms distincts (horloge avancée entre chaque).
    $produites = [];
    foreach ([0, 1, 2] as $i) {
        $this->travel(1)->minutes();
        Artisan::call('db:backup');
        $produites[] = array_values(array_diff(MysqlReel::sauvegardes(), $produites))[0];
    }
    $this->travelBack();

    // Horodatages de fichier choisis pour que les trois tris possibles divergent :
    //   par horodatage  : 03/11, 22/10, 01/10  → [0, 2, 1]  (attendu)
    //   par nom décroissant                    → [2, 1, 0]
    //   par date « d/m/Y » comparée en chaîne  : « 22/10 » > « 03/11 » > « 01/10 » → [2, 0, 1]
    $dir = storage_path('app/backups');
    touch("{$dir}/{$produites[0]}", strtotime('2026-11-03 10:00:00'));
    touch("{$dir}/{$produites[1]}", strtotime('2026-10-01 10:00:00'));
    touch("{$dir}/{$produites[2]}", strtotime('2026-10-22 10:00:00'));
    clearstatcache();

    expect(nomsListes($this))->toBe([$produites[0], $produites[2], $produites[1]]);
});
