<?php

namespace App\Services;

use App\Support\PdfAssets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Signatures électroniques des signataires, configurées dans les Paramètres.
 *
 * Pour chaque poste du circuit (contrôleur, DAF, DG) on enregistre le nom, les
 * prénoms, la fonction imprimée et l'image de signature manuscrite scannée.
 * Ce sont les titulaires EN POSTE : le document porte leur identité officielle,
 * indépendamment du compte utilisateur qui a cliqué.
 *
 * Les images vivent sur le disque PRIVÉ, sous storage/app/signatures, et ne sont
 * jamais exposées par une URL publique : elles sont embarquées en base64 dans le
 * PDF au moment de la génération.
 */
class SignatureParametres
{
    /** Postes configurables du circuit. */
    public const POSTES = ['controleur', 'daf', 'dg'];

    /** Libellés par défaut, si la fonction n'est pas renseignée. */
    private const FONCTIONS_PAR_DEFAUT = [
        'controleur' => 'Le Contrôleur',
        'daf' => "Le Directeur de l'Administration et des Finances",
        'dg' => 'Le Directeur Général',
    ];

    private const DOSSIER = 'signatures';

    /**
     * Paraphes archivés au moment d'une signature, un par état et par poste.
     * Sous `signatures/` : PdfAssets n'embarque que ce qui vit dans ce dossier.
     */
    private const DOSSIER_ARCHIVES = 'signatures/etats';

    private const CHEMIN_PARAMETRES = 'settings.json';

    /** Formats acceptés pour une image de signature. */
    private const MIMES_AUTORISES = ['image/png', 'image/jpeg'];

    /** 500 Ko : une signature scannée n'a aucune raison d'être plus lourde. */
    private const TAILLE_MAX = 512000;

    /**
     * Identité et signature d'un poste.
     *
     * @return array{nom:string, prenoms:string, nom_complet:string, fonction:string, image:?string}
     */
    public static function pour(string $poste): array
    {
        $parametres = self::lire();

        $nom = trim((string) ($parametres["{$poste}Nom"] ?? ''));
        $prenoms = trim((string) ($parametres["{$poste}Prenoms"] ?? ''));
        $fonction = trim((string) ($parametres["{$poste}Fonction"] ?? ''));

        // « Prénoms NOM » : l'ordre retenu sur l'état de référence
        // (ex. « Euloge D. ATOHOUN », « Richard DADA »).
        $nomComplet = trim($prenoms.' '.$nom);

        return [
            'nom' => $nom,
            'prenoms' => $prenoms,
            'nom_complet' => $nomComplet,
            'fonction' => $fonction !== '' ? $fonction : (self::FONCTIONS_PAR_DEFAUT[$poste] ?? ''),
            'image' => PdfAssets::signatureDataUri(self::cheminImage($poste)),
        ];
    }

    /**
     * Archive le paraphe d'un poste pour un état donné, et renvoie son chemin.
     *
     * Le paraphe configuré dans les Paramètres est celui du titulaire EN POSTE :
     * il change quand la personne change. Un document signé, lui, ne change plus.
     * On en prend donc une copie au moment de la signature, que le PDF relira
     * ensuite — c'est ce qui garantit qu'un état validé porte toujours le
     * paraphe de celui dont il porte le nom.
     *
     * Renvoie null si aucun paraphe n'est configuré (le PDF imprime alors la
     * seule ligne de signature, comme auparavant).
     */
    public static function archiverPourEtat(string $poste, int $etatId): ?string
    {
        self::exigerPosteValide($poste);

        $source = self::cheminImage($poste);

        if ($source === null) {
            return null;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
        $archive = self::DOSSIER_ARCHIVES."/{$etatId}-{$poste}.{$extension}";

        // Une signature antérieure sur le même état (état rejeté puis resigné)
        // n'a plus lieu d'être : on ne conserve que le paraphe en vigueur.
        self::purgerArchiveEtat($poste, $etatId);

        Storage::disk('local')->put($archive, (string) Storage::disk('local')->get($source));

        return $archive;
    }

    /** Retire le paraphe archivé d'un poste pour un état (rejet, resignature). */
    public static function purgerArchiveEtat(string $poste, int $etatId): void
    {
        self::exigerPosteValide($poste);

        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            $chemin = self::DOSSIER_ARCHIVES."/{$etatId}-{$poste}.{$extension}";

            if (Storage::disk('local')->exists($chemin)) {
                Storage::disk('local')->delete($chemin);
            }
        }
    }

    /** Les trois postes, pour l'affichage dans l'écran Paramètres. */
    public static function tous(): array
    {
        $resultat = [];

        foreach (self::POSTES as $poste) {
            $signataire = self::pour($poste);
            // On n'expose jamais l'image elle-même dans l'API des paramètres,
            // seulement le fait qu'une signature est enregistrée.
            unset($signataire['image']);
            $signataire['signature_enregistree'] = self::aUneSignature($poste);
            $resultat[$poste] = $signataire;
        }

        return $resultat;
    }

    /** Une image de signature est-elle enregistrée pour ce poste ? */
    public static function aUneSignature(string $poste): bool
    {
        return PdfAssets::signatureDataUri(self::cheminImage($poste)) !== null;
    }

    /**
     * Enregistre l'image de signature d'un poste.
     *
     * Le type est déterminé par le CONTENU réel du fichier, jamais par son
     * extension : une extension est déclarative, un en-tête d'image ne l'est pas.
     */
    public static function enregistrerImage(string $poste, UploadedFile $fichier): string
    {
        self::exigerPosteValide($poste);

        if ($fichier->getSize() > self::TAILLE_MAX) {
            throw ValidationException::withMessages([
                'signature' => ['Image trop volumineuse (500 Ko maximum).'],
            ]);
        }

        $info = @getimagesize($fichier->getRealPath());
        $mime = $info['mime'] ?? null;

        if (! in_array($mime, self::MIMES_AUTORISES, true)) {
            throw ValidationException::withMessages([
                'signature' => ['Format non pris en charge : fournissez une image PNG ou JPEG.'],
            ]);
        }

        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $chemin = self::DOSSIER."/{$poste}.{$extension}";

        // Une seule signature par poste : on retire l'ancienne, quel que soit
        // son format, pour ne pas laisser deux fichiers concurrents.
        self::supprimerImage($poste);

        Storage::disk('local')->put($chemin, file_get_contents($fichier->getRealPath()));

        return $chemin;
    }

    /** Retire l'image de signature d'un poste, quel que soit son format. */
    public static function supprimerImage(string $poste): void
    {
        self::exigerPosteValide($poste);

        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            $chemin = self::DOSSIER."/{$poste}.{$extension}";

            if (Storage::disk('local')->exists($chemin)) {
                Storage::disk('local')->delete($chemin);
            }
        }
    }

    /** Chemin relatif de l'image du poste, ou null s'il n'en existe aucune. */
    private static function cheminImage(string $poste): ?string
    {
        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            $chemin = self::DOSSIER."/{$poste}.{$extension}";

            if (Storage::disk('local')->exists($chemin)) {
                return $chemin;
            }
        }

        return null;
    }

    private static function exigerPosteValide(string $poste): void
    {
        if (! in_array($poste, self::POSTES, true)) {
            throw ValidationException::withMessages([
                'poste' => ['Poste inconnu : attendu controleur, daf ou dg.'],
            ]);
        }
    }

    /** @return array<string,mixed> */
    private static function lire(): array
    {
        $chemin = storage_path('app/'.self::CHEMIN_PARAMETRES);

        if (! is_file($chemin)) {
            return [];
        }

        return json_decode((string) file_get_contents($chemin), true) ?: [];
    }
}
