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
     */
    protected function libelle(string $nom, string $prenoms, string $theme): string
    {
        $texte = Str::ascii("{$nom} {$prenoms} {$theme}");
        $texte = strtoupper($texte);
        $texte = str_replace(',', ' ', $texte);

        return Str::squish($texte);
    }

    /** Slug ASCII pour le nom de fichier : "{THEME}_{TRANCHE}.{ext}". */
    protected function nomFichier(string $theme, string $tranche, string $extension): string
    {
        $slug = Str::slug($theme) ?: 'mission';

        return "{$slug}_{$tranche}.{$extension}";
    }
}
