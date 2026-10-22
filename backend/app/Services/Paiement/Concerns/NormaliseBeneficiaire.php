<?php

namespace App\Services\Paiement\Concerns;

use Illuminate\Support\Str;

trait NormaliseBeneficiaire
{
    /** MSISDN = indicatif (229) + téléphone national 10 chiffres (0 initial conservé). */
    protected function msisdn(string $telephone): string
    {
        return config('paiement.indicatif').$telephone;
    }

    /**
     * Libellé bénéficiaire : "{NOM} {PRENOMS} {THEME}" en MAJUSCULES, ASCII,
     * espaces simples, sans virgule (réservée au séparateur). Sans limite de longueur.
     *
     * Anti-injection de formule (CSV/Excel) : les fichiers générés sont ouverts hors
     * de l'application (tableur de l'opérateur). Une valeur débutant par = + - @ y serait
     * réinterprétée comme formule (HYPERLINK/DDE → exfiltration ou exécution). On préfixe
     * donc d'une apostrophe tout libellé commençant par un caractère dangereux.
     */
    protected function libelle(string $nom, string $prenoms, string $theme): string
    {
        $texte = Str::ascii("{$nom} {$prenoms} {$theme}");
        $texte = strtoupper($texte);
        $texte = str_replace(',', ' ', $texte);
        $texte = Str::squish($texte);

        if ($texte !== '' && in_array($texte[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $texte = "'".$texte;
        }

        return $texte;
    }

    /**
     * Slug ASCII pour le nom de fichier : "{THEME}_{TRANCHE}.{ext}", suffixé
     * "_COMPL{n}" pour un lot complémentaire (séquence ≥ 2).
     */
    protected function nomFichier(string $theme, string $tranche, string $extension, int $sequence = 1): string
    {
        $slug = Str::slug($theme) ?: 'mission';
        $suffixe = $sequence > 1 ? "_COMPL{$sequence}" : '';

        return "{$slug}_{$tranche}{$suffixe}.{$extension}";
    }
}
