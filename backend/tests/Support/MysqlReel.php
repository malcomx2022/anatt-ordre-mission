<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/*
 * Socle de la suite « mysql » (tests/Mysql) : ce qui ne se prouve que sur un vrai
 * serveur MySQL / MariaDB — binaires mysqldump / mysql de db:backup et db:restore,
 * verrous InnoDB (SELECT … FOR UPDATE).
 *
 * Sous SQLite (la suite habituelle, phpunit.xml), chaque test est IGNORÉ avant
 * toute écriture : `./vendor/bin/pest` reste inchangé. Pour la lancer :
 *
 *   DB_CONNECTION=mysql DB_DATABASE=anatt_test_mysql DB_USERNAME=root DB_PASSWORD= \
 *     vendor/bin/pest --group=mysql
 *
 * La base est REMISE À ZÉRO (migrate:fresh) avant chaque test, sans transaction
 * englobante (RefreshDatabase la garderait ouverte : mysqldump, qui lit depuis une
 * autre session, ne verrait rien, et le DROP TABLE de la restauration attendrait
 * son verrou de métadonnées). Garde-fou : le nom de la base doit contenir « test ».
 */
final class MysqlReel
{
    /** Nom de la seconde connexion, qui joue la session concurrente. */
    public const CONNEXION_CONCURRENTE = 'mysql_concurrente';

    /** Ignore le test hors MySQL/MariaDB ; sinon repart d'une base migrée vierge. */
    public static function preparer(PhpUnitTestCase $test): void
    {
        $connexion = DB::connection();

        if (! in_array($connexion->getDriverName(), ['mysql', 'mariadb'], true)) {
            $test->markTestSkipped('Suite MySQL : connexion « '.$connexion->getDriverName().' » (lancer avec DB_CONNECTION=mysql).');
        }

        $base = (string) $connexion->getDatabaseName();
        if (! str_contains(strtolower($base), 'test')) {
            $test->markTestSkipped("Suite MySQL : la base « {$base} » n'est pas une base de test (son nom doit contenir « test ») — elle serait effacée.");
        }

        // db:backup / db:restore lisent la connexion « mysql » : on l'aligne sur
        // la connexion courante (utile si DB_CONNECTION=mariadb).
        config(['database.connections.mysql' => array_merge(
            (array) config('database.connections.mysql'),
            array_intersect_key($connexion->getConfig(), array_flip(['host', 'port', 'database', 'username', 'password'])),
        )]);

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    /**
     * Seconde session sur la même base (autre connexion PDO), avec une attente de
     * verrou courte : un SELECT … FOR UPDATE bloqué échoue au bout de $timeout s
     * (erreur 1205) au lieu d'attendre 50 s.
     */
    public static function sessionConcurrente(int $timeout = 1): Connection
    {
        config(['database.connections.'.self::CONNEXION_CONCURRENTE => DB::connection()->getConfig()]);
        DB::purge(self::CONNEXION_CONCURRENTE);

        $session = DB::connection(self::CONNEXION_CONCURRENTE);
        $session->statement('SET SESSION innodb_lock_wait_timeout = '.$timeout);

        return $session;
    }

    /** Vrai si l'exception est une attente de verrou expirée (MySQL/MariaDB 1205). */
    public static function estAttenteDeVerrouExpiree(\Throwable $e): bool
    {
        $pdo = $e instanceof \PDOException ? $e : $e->getPrevious();

        return $pdo instanceof \PDOException && (int) ($pdo->errorInfo[1] ?? 0) === 1205;
    }

    /** Vide le répertoire des sauvegardes (storage/app/backups). */
    public static function viderSauvegardes(): void
    {
        foreach (glob(storage_path('app/backups').'/*') ?: [] as $fichier) {
            @unlink($fichier);
        }
    }

    /**
     * Contenu SQL d'une sauvegarde (.sql ou .sql.gz).
     */
    public static function lireSauvegarde(string $nom): string
    {
        $chemin = storage_path('app/backups/'.$nom);
        $contenu = (string) file_get_contents($chemin);

        return str_ends_with($nom, '.gz') ? (string) gzdecode($contenu) : $contenu;
    }

    /**
     * Sauvegardes présentes, par nom.
     *
     * @return string[]
     */
    public static function sauvegardes(): array
    {
        $fichiers = array_map('basename', glob(storage_path('app/backups').'/backup_*.sql*') ?: []);
        sort($fichiers);

        return $fichiers;
    }
}
