<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Paramètres généraux de l'application, lus depuis settings.json.
 *
 * Les valeurs sont mises en cache 60 secondes pour éviter de lire le
 * fichier à chaque calcul d'indemnité — le paramètre change rarement
 * et une staleness d'une minute est sans impact financier.
 */
class Parametres
{
    private const FICHIER = 'settings.json';

    private const CLE = 'partAvance';

    private const VALEUR_PAR_DEFAUT = 70;

    /** Pourcentage de l'avance (le reliquat absorbe l'arrondi). */
    public static function partAvance(): int
    {
        return Cache::remember('parametres.part_avance', 60, fn () => self::lire());
    }

    /** Invalidation du cache après écriture. */
    public static function invalidateCache(): void
    {
        Cache::forget('parametres.part_avance');
    }

    private static function lire(): int
    {
        $chemin = storage_path('app/'.self::FICHIER);

        if (! is_file($chemin)) {
            return self::VALEUR_PAR_DEFAUT;
        }

        $settings = json_decode((string) file_get_contents($chemin), true) ?: [];
        $valeur = $settings[self::CLE] ?? self::VALEUR_PAR_DEFAUT;

        return is_int($valeur) && $valeur >= 0 && $valeur <= 100 ? $valeur : self::VALEUR_PAR_DEFAUT;
    }
}
