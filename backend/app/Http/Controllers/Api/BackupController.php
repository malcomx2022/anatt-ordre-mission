<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sauvegardes de la base de données (SUPER_ADMIN, permission parametres.gerer).
 *
 * Les fichiers vivent sur le disque local (storage/app/backups). La création
 * délègue à la commande artisan `db:backup`, la restauration à `db:restore`
 * (qui sauvegarde d'abord l'état courant : l'opération reste réversible).
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

        // Tri sur l'horodatage du fichier, pas sur la date affichée : « d/m/Y »
        // comparé comme une chaîne classerait le 22/10 avant le 03/11.
        $files = collect(scandir($dir))->filter(fn ($f) => str_ends_with($f, '.sql') || str_ends_with($f, '.sql.gz'))
            ->sortByDesc(fn ($f) => filemtime("{$dir}/{$f}"))
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
            })->values();

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

        return response()->json(['message' => 'Sauvegarde créée.', 'details' => $sortie]);
    }

    /** Télécharge une sauvegarde (copie hors serveur). */
    public function download(string $name): BinaryFileResponse
    {
        return response()->download($this->cheminExistant($name));
    }

    /**
     * Restaure la base depuis une sauvegarde. Opération destructrice : le corps
     * doit recopier le nom du fichier (`confirmation`), et `db:restore` prend
     * une sauvegarde de sécurité de l'état courant avant d'écraser la base.
     */
    public function restaurer(Request $request, string $name): JsonResponse
    {
        $chemin = $this->cheminExistant($name);
        $fichier = basename($chemin);

        $request->validate(['confirmation' => 'required|string|in:'.$fichier], [
            'confirmation.in' => 'Recopiez exactement le nom du fichier pour confirmer la restauration.',
        ]);

        $code = Artisan::call('db:restore', ['fichier' => $fichier, '--force' => true]);
        $sortie = trim(Artisan::output());

        if ($code !== 0) {
            return response()->json(['message' => 'Échec de la restauration.', 'details' => $sortie], 500);
        }

        // Journalisé APRÈS la restauration : une écriture antérieure aurait été
        // écrasée avec le reste de la base.
        // L'auteur peut ne pas exister dans la base restaurée : on ne référence
        // alors aucun utilisateur (clé étrangère), son nom reste dans les données.
        AuditService::log(
            User::find($request->user()->id),
            'BACKUP_RESTORE',
            'backup',
            null,
            ['fichier' => $fichier, 'par' => $request->user()->name],
        );

        return response()->json(['message' => 'Base restaurée. Une sauvegarde de l’état précédent a été créée.', 'details' => $sortie]);
    }

    /**
     * Chemin d'une sauvegarde existante : `basename` (pas de traversée de
     * répertoire) et extension .sql / .sql.gz exigée.
     */
    private function cheminExistant(string $name): string
    {
        $fichier = basename($name);

        abort_unless(
            str_ends_with($fichier, '.sql') || str_ends_with($fichier, '.sql.gz'),
            422,
            'Nom de sauvegarde invalide (extension .sql ou .sql.gz attendue).',
        );

        $chemin = $this->repertoire().'/'.$fichier;
        abort_unless(is_file($chemin), 404, 'Sauvegarde introuvable.');

        return $chemin;
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

        return response()->json(['message' => 'Sauvegarde supprimée.']);
    }
}
