<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--compress : Compresser le fichier avec gzip (si disponible)}';

    protected $description = 'Sauvegarder la base de données ANaTT';

    public function handle(): int
    {
        $cfg = config('database.connections.mysql');
        $database = $cfg['database'] ?? 'anatt_missions';
        $username = $cfg['username'] ?? 'root';
        $password = (string) ($cfg['password'] ?? '');
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (string) ($cfg['port'] ?? '3306');
        $binary = config('database.dump_binary', 'mysqldump');

        // Répertoire unique, cohérent avec la route GET /admin/backups.
        $dir = storage_path('app/backups');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Impossible de créer le répertoire de sauvegarde : {$dir}");

            return self::FAILURE;
        }

        // Nom à la seconde ; deux sauvegardes dans la même seconde (dont la sauvegarde de
        // sécurité de db:restore) ne doivent jamais écraser un fichier existant : suffixe -2, -3…
        $timestamp = now()->format('Y-m-d_His');
        $base = "backup_{$timestamp}";
        for ($n = 2; is_file("{$dir}/{$base}.sql") || is_file("{$dir}/{$base}.sql.gz"); $n++) {
            $base = "backup_{$timestamp}-{$n}";
        }
        $filename = "{$base}.sql";
        $filepath = $dir.DIRECTORY_SEPARATOR.$filename;
        $errfile = $filepath.'.err';

        // stdout -> fichier .sql ; stderr -> fichier .err (ne pollue pas le dump).
        $passwordArg = $password !== '' ? '-p'.escapeshellarg($password) : '';
        $command = sprintf(
            '%s -h %s -P %s -u %s %s %s > %s 2> %s',
            escapeshellarg($binary),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $passwordArg,
            escapeshellarg($database),
            escapeshellarg($filepath),
            escapeshellarg($errfile),
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $err = is_file($errfile) ? trim((string) file_get_contents($errfile)) : '';
            @unlink($errfile);
            @unlink($filepath); // dump vide/partiel
            $message = $err ?: "code {$returnCode} — vérifiez DB_DUMP_BINARY (mysqldump introuvable ?).";
            $this->error('Échec du backup : '.$message);
            Log::error('Database backup failed', ['code' => $returnCode, 'stderr' => $err]);

            return self::FAILURE;
        }
        @unlink($errfile);

        // Compression best-effort : si gzip est indisponible, on conserve le .sql.
        if ($this->option('compress')) {
            exec('gzip '.escapeshellarg($filepath), $g, $gzipCode);
            if ($gzipCode === 0 && is_file($filepath.'.gz')) {
                $filename .= '.gz';
                $filepath .= '.gz';
            } else {
                $this->warn('gzip indisponible : sauvegarde conservée en .sql non compressé.');
            }
        }

        $size = is_file($filepath) ? $this->formatSize(filesize($filepath)) : '0 B';
        $this->cleanOldBackups($dir, 30);

        $this->info("Backup créé : {$filename} ({$size})");
        Log::info('Database backup created', ['file' => $filename, 'size' => $size]);

        return self::SUCCESS;
    }

    /** Supprime les sauvegardes plus anciennes que la rétention (jours). */
    private function cleanOldBackups(string $dir, int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays)->timestamp;
        foreach (glob($dir.DIRECTORY_SEPARATOR.'backup_*.sql*') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $this->line('Ancien backup supprimé : '.basename($file));
            }
        }
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
