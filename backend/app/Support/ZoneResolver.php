<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Déduction du code de zone du barème à partir d'une destination libre.
 * S'appuie sur la table de correspondance config('benin.zones').
 */
class ZoneResolver
{
    /** Déduit le code de zone, ou null si la destination est inconnue. */
    public static function deduire(?string $destination): ?string
    {
        if (! $destination) {
            return null;
        }

        $cible = self::normaliser($destination);

        foreach (config('benin.zones', []) as $code => $destinations) {
            foreach ($destinations as $candidat) {
                $candidat = self::normaliser($candidat);
                // Correspondance exacte ou destination contenant le libellé de référence.
                if ($cible === $candidat || str_contains($cible, $candidat)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /** Portée (INTERIEUR / EXTERIEUR) déduite d'un code de zone. */
    public static function portee(string $zoneCode): string
    {
        return $zoneCode === 'INTERIEUR' ? 'INTERIEUR' : 'EXTERIEUR';
    }

    private static function normaliser(string $valeur): string
    {
        // Sans accents, en majuscules, espaces normalisés.
        return Str::squish(Str::upper(Str::ascii($valeur)));
    }
}
