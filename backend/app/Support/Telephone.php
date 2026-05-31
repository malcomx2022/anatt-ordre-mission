<?php

namespace App\Support;

/**
 * Utilitaires de téléphonie mobile béninoise (format national 10 chiffres).
 * Les tranches d'opérateur proviennent de config/benin.php.
 */
class Telephone
{
    /** Le numéro respecte-t-il le format national (10 chiffres, débute par « 01 ») ? */
    public static function estValide(?string $tel): bool
    {
        return is_string($tel) && preg_match(config('benin.telephone.regex'), $tel) === 1;
    }

    /** Opérateur déduit du numéro, ou null si inconnu/invalide. */
    public static function operateurPour(?string $tel): ?string
    {
        if (! self::estValide($tel)) {
            return null;
        }

        $prefixe = substr($tel, 2, 2); // 2 chiffres après « 01 »

        foreach (config('benin.operateurs') as $operateur => $prefixes) {
            if (in_array($prefixe, $prefixes, true)) {
                return $operateur;
            }
        }

        return null;
    }

    /** Le couple numéro/opérateur est-il cohérent ? */
    public static function estCoherent(?string $tel, ?string $operateur): bool
    {
        $deduit = self::operateurPour($tel);

        return $deduit !== null && $deduit === $operateur;
    }
}
