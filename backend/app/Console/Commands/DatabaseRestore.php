<?php

namespace App\Console\Commands;

use App\Support\PermissionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Restaure la base depuis une sauvegarde de storage/app/backups (.sql / .sql.gz).
 *
 * Une sauvegarde de sécurité de l'état COURANT est prise juste avant : une
 * restauration malencontreuse se défait donc en restaurant ce fichier.
 */
class DatabaseRestore extends Command
{
    protected $signature = 'db:restore {fichier : Nom du fichier dans storage/app/backups}
                            {--force : Ne pas demander de confirmation}
                            {--sans-sauvegarde : Ne pas sauvegarder l\'état courant avant (déconseillé)}';

    protected $description = 'Restaurer la base de donnees ANaTT depuis une sauvegarde';

    public function handle(): int
    {
        $fichier = basename((string) $this->argument('fichier'));
        $chemin = storage_path('app/backups').DIRECTORY_SEPARATOR.$fichier;

        if (! (str_ends_with($fichier, '.sql') || str_ends_with($fichier, '.sql.gz')) || ! is_file($chemin)) {
            $this->error("Sauvegarde introuvable : {$fichier}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Écraser la base avec {$fichier} ?")) {
            return self::FAILURE;
        }

        if (! $this->option('sans-sauvegarde')) {
            if (Artisan::call('db:backup', ['--compress' => true]) !== 0) {
                $this->error('Sauvegarde de sécurité impossible : restauration annulée. '.trim(Artisan::output()));

                return self::FAILURE;
            }
            $this->info(trim(Artisan::output()));
        }

        $cfg = config('database.connections.mysql');
        $passwordArg = (string) ($cfg['password'] ?? '') !== '' ? '-p'.escapeshellarg((string) $cfg['password']) : '';
        $client = sprintf(
            '%s -h %s -P %s -u %s %s %s',
            escapeshellarg((string) config('database.restore_binary', 'mysql')),
            escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($cfg['port'] ?? '3306')),
            escapeshellarg((string) ($cfg['username'] ?? 'root')),
            $passwordArg,
            escapeshellarg((string) ($cfg['database'] ?? 'anatt_missions')),
        );

        // Une archive .gz est décompressée en PHP (pas de tube shell : sous /bin/sh,
        // l'échec de gzip serait masqué par le code de sortie du client mysql).
        $source = str_ends_with($fichier, '.gz') ? $this->decompresser($chemin) : $chemin;
        if ($source === null) {
            $this->error("Archive illisible : {$fichier}");

            return self::FAILURE;
        }

        $errfile = $chemin.'.restore.err';
        exec(sprintf('%s < %s 2> %s', $client, escapeshellarg($source), escapeshellarg($errfile)), $sortie, $code);

        $erreur = is_file($errfile) ? trim((string) file_get_contents($errfile)) : '';
        @unlink($errfile);
        if ($source !== $chemin) {
            @unlink($source);
        }

        if ($code !== 0) {
            $this->error('Échec de la restauration : '.($erreur ?: "code {$code} — vérifiez DB_RESTORE_BINARY (client mysql introuvable ?)."));
            Log::error('Database restore failed', ['file' => $fichier, 'code' => $code, 'stderr' => $erreur]);

            return self::FAILURE;
        }

        // La matrice des permissions est en cache sans expiration : sans purge,
        // les droits d'avant la restauration resteraient en vigueur.
        app(PermissionRepository::class)->vider();

        $this->info("Base restaurée depuis {$fichier}.");
        Log::warning('Database restored', ['file' => $fichier]);

        return self::SUCCESS;
    }

    /** Décompresse une archive .sql.gz dans un fichier temporaire ; null si illisible. */
    private function decompresser(string $chemin): ?string
    {
        $entree = @gzopen($chemin, 'rb');
        $temp = tempnam(sys_get_temp_dir(), 'anatt_restore_');
        $sortie = $temp !== false ? fopen($temp, 'wb') : false;

        if ($entree === false || $sortie === false) {
            return null;
        }

        while (! gzeof($entree)) {
            $bloc = gzread($entree, 1 << 20);
            if ($bloc === false) {
                gzclose($entree);
                fclose($sortie);
                @unlink($temp);

                return null;
            }
            fwrite($sortie, $bloc);
        }

        gzclose($entree);
        fclose($sortie);

        return $temp;
    }
}
