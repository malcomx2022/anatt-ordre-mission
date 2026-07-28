<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Assets partagés pour les documents PDF (DomPDF).
 *
 * Le logo officiel (bandeau armoiries + ANaTT + tricolore + « République du Bénin »)
 * est embarqué en data-URI base64 : DomPDF n'a alors aucune dépendance à un chemin
 * disque/réseau et le rendu est identique en CLI, en file (queue) et en HTTP.
 *
 * Source : docs/entete anatt.docx (en-tête officiel ANaTT).
 */
class PdfAssets
{
    private static ?string $enteteDataUri = null;

    /** Chemin du bandeau d'en-tête officiel embarqué. */
    private const ENTETE_LOGO = 'views/pdf/images/entete-anatt.jpg';

    /** Coordonnées de l'en-tête officiel (en-tête du docx ANaTT). */
    public const CONTACT = [
        'bp' => '01 BP 1230 Cotonou',
        'tel' => ['+229 01 91 85 11 11', '+229 01 21 31 39 98'],
        'email' => 'anatt.contact@gouv.bj',
        'adresse' => 'Rue 108 - Tokpa Hoho, Lot 35 B - Immeuble KOUGBLENOU',
    ];

    /**
     * Data-URI base64 du bandeau d'en-tête officiel (mémoïsé pour le process).
     * Retourne une chaîne vide si le fichier est introuvable (le template
     * dégrade alors proprement sans casser la génération).
     */
    public static function enteteDataUri(): string
    {
        if (self::$enteteDataUri !== null) {
            return self::$enteteDataUri;
        }

        $chemin = resource_path(self::ENTETE_LOGO);

        if (! is_file($chemin)) {
            return self::$enteteDataUri = '';
        }

        return self::$enteteDataUri = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($chemin));
    }

    /**
     * Data-URI base64 d'une image de signature stockée sur le disque PRIVÉ.
     *
     * L'image est embarquée dans le PDF plutôt que référencée par un chemin :
     * DomPDF n'accède ainsi à aucun fichier disque (isRemoteEnabled reste désactivé)
     * et la signature ne transite jamais par une URL publique.
     *
     * Retourne null si le chemin est vide, hors du dossier autorisé, introuvable
     * ou si le contenu n'est pas une image PNG/JPEG réelle.
     */
    public static function signatureDataUri(?string $chemin): ?string
    {
        if (empty($chemin)) {
            return null;
        }

        // Cloisonnement : on n'embarque que ce qui vit sous storage/app/signatures.
        // Empêche qu'un chemin manipulé en base fasse lire un fichier arbitraire.
        $relatif = ltrim(str_replace('\\', '/', $chemin), '/');
        if (! str_starts_with($relatif, 'signatures/') || str_contains($relatif, '..')) {
            return null;
        }

        // Lecture par la façade Storage, et non par storage_path() : c'est le même
        // canal que l'écriture, donc la lecture reste cohérente quel que soit le
        // disque configuré (y compris un disque simulé en test).
        $disque = Storage::disk('local');

        if (! $disque->exists($relatif)) {
            return null;
        }

        $contenu = (string) $disque->get($relatif);

        // Type déterminé par le contenu réel, jamais par l'extension.
        $info = @getimagesizefromstring($contenu);
        $mime = $info['mime'] ?? null;

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contenu);
    }
}
