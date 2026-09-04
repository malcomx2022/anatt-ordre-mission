<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Sauvegardes de la base de données (SUPER_ADMIN, permission parametres.gerer).
 *
 * Les fichiers vivent sur le disque local (storage/app/backups). La création
 * délègue à la commande artisan `db:backup` ; aucune restauration automatique
 * n'est exposée par l'API.
 */
class BackupController extends Controller
{
    /** Répertoire des sauvegardes sur le disque local. */
    private function repertoire(): string
    {
        return storage_path('app/backups');
    }

    /** Liste des sauvegardes (.sql / .sql.gz), les plus récentes d'abord. */
    public function index(): JsonResponse
    {
        $dir = $this->repertoire();
        if (! is_dir($dir)) {
            return $this->success([]);
        }

        $files = collect(scandir($dir))->filter(fn ($f) => str_ends_with($f, '.sql') || str_ends_with($f, '.sql.gz'))
            ->values()->map(function ($f) use ($dir) {
                $path = "{$dir}/{$f}";
                $size = filesize($path);

                return [
                    'name' => $f,
                    'date' => date('d/m/Y H:i', filemtime($path)),
                    'size' => $size < 1048576 ? round($size / 1024, 1).' KB' : round($size / 1048576, 1).' MB',
                    'type' => str_contains($f, '_020000') ? 'Automatique' : 'Manuel',
                    'status' => 'Complet',
                ];
            })->sortByDesc('date')->values();

        return $this->success($files);
    }

    /** Crée une sauvegarde compressée via la commande artisan `db:backup`. */
    public function store(): JsonResponse
    {
        $code = Artisan::call('db:backup', ['--compress' => true]);
        $sortie = trim(Artisan::output());

        if ($code !== 0) {
            return response()->json([
                'message' => 'Échec de la sauvegarde.',
                'details' => $sortie,
            ], 500);
        }

        return response()->json(['message' => 'Sauvegarde creee.', 'details' => $sortie]);
    }

    /**
     * Supprime une sauvegarde.
     *  - `basename` : pas de traversée de répertoire.
     *  - extension .sql / .sql.gz exigée : on ne supprime que des sauvegardes,
     *    jamais un autre fichier qui traînerait dans le dossier.
     */
    public function destroy(string $name): JsonResponse
    {
        $fichier = basename($name);

        abort_unless(
            str_ends_with($fichier, '.sql') || str_ends_with($fichier, '.sql.gz'),
            422,
            'Nom de sauvegarde invalide (extension .sql ou .sql.gz attendue).',
        );

        $path = $this->repertoire().'/'.$fichier;
        if (file_exists($path)) {
            unlink($path);
        }

        return response()->json(['message' => 'Sauvegarde supprimee.']);
    }
}
