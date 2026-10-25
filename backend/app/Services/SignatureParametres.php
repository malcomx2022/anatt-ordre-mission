<?php

namespace App\Services;

use App\Models\EtatPaiement;
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

    private const CHEMIN_PARAMETRES = 'settings.json';

    /** Formats acceptés pour une image de signature. */
    private const MIMES_AUTORISES = ['image/png', 'image/jpeg'];

    /** 500 Ko : une signature scannée n'a aucune raison d'être plus lourde. */
    private const TAILLE_MAX = 512000;

    /**
     * Identité et signature d'un poste.
     *
     * `fonction` porte toujours un libellé (celui des Paramètres, sinon le défaut du
     * poste) ; `fonction_saisie` ne porte que la saisie des Paramètres, vide sinon.
     *
     * @return array{nom:string, prenoms:string, nom_complet:string, fonction:string, fonction_saisie:string, image:?string}
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
            'fonction_saisie' => $fonction,
            'image' => PdfAssets::signatureDataUri(self::cheminImage($poste)),
        ];
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

    /**
     * Archive une copie du paraphe en poste au moment où la personne signe.
     *
     * Le document validé doit toujours imprimer le paraphe tel qu'il était au
     * moment de la signature, même si le titulaire du poste change ensuite :
     * on ne relie jamais l'image « courante » pour un état déjà signé.
     *
     * Retourne le chemin de l'archive (disque privé), ou null si le poste n'a
     * aucune image enregistrée — l'état tombera alors sur le bloc sans paraphe.
     */
    public static function archiverPour(EtatPaiement $etat, string $poste): ?string
    {
        self::exigerPosteValide($poste);

        $source = self::cheminImage($poste);

        if ($source === null) {
            return null;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $chemin = self::DOSSIER."/etats/{$etat->id}-{$poste}.{$extension}";

        Storage::disk('local')->put($chemin, Storage::disk('local')->get($source));

        return $chemin;
    }

    /**
     * Supprime les archives de paraphe d'un état (rejet : les signatures sont
     * annulées, une signature manuscrite ne se conserve pas sans raison d'être).
     */
    public static function supprimerArchives(EtatPaiement $etat, array $chemins): void
    {
        foreach (array_filter($chemins) as $chemin) {
            // On ne supprime que les archives d'états, jamais le paraphe en poste.
            if (! str_starts_with((string) $chemin, self::DOSSIER.'/etats/')) {
                continue;
            }

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
