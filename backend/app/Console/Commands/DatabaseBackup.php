<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--compress : Compresser le fichier avec gzip}';

    protected $description = 'Sauvegarder la base de donnees ANaTT';

    public function handle(): int
    {
        $database = config('database.connections.mysql.database', 'anatt_missions');
        $username = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$timestamp}.sql";
        $directory = 'backups';

        // Creer le repertoire si necessaire
        Storage::disk('local')->makeDirectory($directory);
        $filepath = storage_path("app/{$directory}/{$filename}");

        // Construire la commande mysqldump
        $passwordArg = $password ? "-p" . escapeshellarg($password) : '';
        $command = sprintf(
            'mysqldump -h %s -P %s -u %s %s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            $passwordArg,
            escapeshellarg($database),
            escapeshellarg($filepath),
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error("Echec du backup: " . implode("\n", $output));
            Log::error("Database backup failed", ['output' => $output, 'code' => $returnCode]);
            return self::FAILURE;
        }

        // Compression optionnelle
        if ($this->option('compress') && file_exists($filepath)) {
            exec("gzip " . escapeshellarg($filepath));
            $filename .= '.gz';
            $filepath .= '.gz';
        }

        $size = file_exists($filepath) ? $this->formatSize(filesize($filepath)) : '0 B';

        // Nettoyage des anciens backups (garder 30 jours)
        $this->cleanOldBackups($directory, 30);

        $this->info("Backup cree: {$filename} ({$size})");
        Log::info("Database backup created", ['file' => $filename, 'size' => $size]);

        return self::SUCCESS;
    }

    private function cleanOldBackups(string $directory, int $retentionDays): void
    {
        $files = Storage::disk('local')->files($directory);
        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < $cutoff) {
                Storage::disk('local')->delete($file);
                $this->line("Ancien backup supprime: " . basename($file));
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
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
