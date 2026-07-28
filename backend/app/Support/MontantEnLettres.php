<?php

namespace App\Support;

/**
 * Conversion d'un montant entier en lettres, en français.
 *
 * Sert la mention d'arrêt de l'état de paiement ANaTT :
 *   « Arrêté le présent état de paiement à la somme de : Un million trois cent
 *     vingt mille (1 320 000) FRANCS CFA. »
 *
 * Règles d'accord appliquées (les pièges classiques du français) :
 *   - « quatre-vingts » prend un s en fin de nombre (80, 280), pas s'il est
 *     suivi (81, 85, quatre-vingt-dix) ;
 *   - « cent » prend un s au pluriel en fin de nombre (200, 300), pas s'il est
 *     suivi d'un autre nombre (201 → deux cent un) ;
 *   - « mille » est TOUJOURS invariable, et l'on ne dit jamais « un mille » ;
 *   - « million » / « milliard » sont des noms : ils s'accordent (deux millions) ;
 *   - conséquence de la distinction adjectif numéral / nom : « cent » et
 *     « vingt » PERDENT leur s devant « mille » mais le CONSERVENT devant
 *     « million(s) » et « milliard(s) » — d'où « quatre-vingt mille » face à
 *     « quatre-vingts millions », et « deux cent mille » face à
 *     « deux cents millions ».
 */
class MontantEnLettres
{
    private const UNITES = [
        0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre',
        5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf',
        10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize',
        14 => 'quatorze', 15 => 'quinze', 16 => 'seize',
    ];

    private const DIZAINES = [
        2 => 'vingt', 3 => 'trente', 4 => 'quarante',
        5 => 'cinquante', 6 => 'soixante', 8 => 'quatre-vingt',
    ];

    /** Paliers, du plus grand au plus petit. */
    private const PALIERS = [
        1_000_000_000 => ['milliard', true],
        1_000_000 => ['million', true],
        1_000 => ['mille', false],   // invariable
    ];

    /**
     * Montant en lettres, sans devise ni majuscule initiale forcée.
     * Un montant négatif est préfixé de « moins ».
     */
    public static function convertir(int $montant): string
    {
        if ($montant < 0) {
            return 'moins '.self::convertir(-$montant);
        }

        return self::nombre($montant, devantMille: false);
    }

    /**
     * @param  bool  $devantMille  true si le groupe est immédiatement suivi de
     *                             « mille » : « cent » et « vingt » perdent alors
     *                             leur s, ce qui n'est pas le cas devant un nom
     *                             (million, milliard).
     */
    private static function nombre(int $n, bool $devantMille): string
    {
        if ($n < 1000) {
            return self::centaines($n, $devantMille);
        }

        foreach (self::PALIERS as $valeur => [$nom, $variable]) {
            if ($n < $valeur) {
                continue;
            }

            $quotient = intdiv($n, $valeur);
            $reste = $n % $valeur;

            // « mille » et non « un mille » ; « un million » se dit bien.
            $prefixe = ($quotient === 1 && ! $variable)
                ? $nom
                : self::nombre($quotient, devantMille: $nom === 'mille')
                    .' '.$nom.($variable && $quotient > 1 ? 's' : '');

            return $reste === 0 ? $prefixe : $prefixe.' '.self::nombre($reste, $devantMille);
        }

        return self::centaines($n, $devantMille);
    }

    /**
     * Mention d'arrêt complète, telle qu'imprimée sur l'état de paiement.
     * Ex. : « Un million trois cent vingt mille (1 320 000) FRANCS CFA »
     */
    public static function mention(int $montant): string
    {
        $lettres = self::convertir($montant);
        $chiffres = number_format($montant, 0, ',', ' ');

        return mb_strtoupper(mb_substr($lettres, 0, 1)).mb_substr($lettres, 1)
            ." ({$chiffres}) FRANCS CFA";
    }

    /** 0 à 999. */
    private static function centaines(int $n, bool $devantMille): string
    {
        if ($n < 100) {
            return self::dizaines($n, $devantMille);
        }

        $centaines = intdiv($n, 100);
        $reste = $n % 100;

        // « cent » invariable au singulier. Pluriel seulement s'il termine le
        // nombre ET qu'il n'est pas suivi de « mille ».
        $pluriel = $centaines > 1 && $reste === 0 && ! $devantMille;

        $mot = $centaines === 1
            ? 'cent'
            : self::UNITES[$centaines].' cent'.($pluriel ? 's' : '');

        return $reste === 0 ? $mot : $mot.' '.self::dizaines($reste, $devantMille);
    }

    /** 0 à 99. */
    private static function dizaines(int $n, bool $devantMille): string
    {
        if ($n <= 16) {
            return self::UNITES[$n];
        }

        // 70-79 et 90-99 se construisent sur soixante et quatre-vingt.
        $base = match (true) {
            $n < 20 => 1,
            $n < 70 => intdiv($n, 10),
            $n < 80 => 6,   // soixante-dix .. soixante-dix-neuf
            default => 8,   // quatre-vingt-dix .. quatre-vingt-dix-neuf
        };

        if ($base === 1) {
            return 'dix-'.self::UNITES[$n - 10];   // 17, 18, 19
        }

        $mot = self::DIZAINES[$base];
        $reste = $n - $base * 10;

        if ($reste === 0) {
            // « quatre-vingts » prend un s, sauf devant « mille ».
            return ($base === 8 && ! $devantMille) ? $mot.'s' : $mot;
        }

        // Liaison « et » pour 21, 31, 41, 51, 61 (reste 1) et pour 71
        // (reste 11 sur la base soixante). Jamais pour 81 ni 91.
        $liaison = (($reste === 1 || $reste === 11) && $base !== 8) ? ' et ' : '-';

        // 11 donne « soixante et onze » / « quatre-vingt-onze ».
        return $mot.$liaison.self::dizaines($reste, $devantMille);
    }
}
