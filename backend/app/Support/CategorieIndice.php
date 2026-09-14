<?php

namespace App\Support;

/**
 * Source de vérité des catégories d'indice ANaTT.
 *
 * La catégorie est reprise telle quelle de SEMO ; à défaut elle se déduit de
 * l'indice de l'agent (prioritaire) ou, en secours, de son salaire brut.
 * Les seuils vivaient jusqu'ici dupliqués dans le modèle Agent, les règles de
 * validation, le miroir frontend et le générateur de l'état officiel : ils
 * n'existent plus qu'ici côté serveur (le miroir TS reste `utils/benin.ts`).
 *
 * Nommage : le code porte le seuil qu'il désigne (INDICE_SUP_800…), de sorte
 * qu'un export, un log ou une requête SQL se lisent sans table de concordance.
 * L'ancien vocabulaire ORDRE_1/2/3 n'est plus produit ; il reste seulement
 * ACCEPTÉ en entrée (cf. normaliser()) pour les fichiers d'import et les
 * filtres déjà en circulation.
 */
class CategorieIndice
{
    // ── Codes ──
    public const SUP_800 = 'INDICE_SUP_800';

    public const DE_400_A_799 = 'INDICE_400_799';

    public const INF_400 = 'INDICE_INF_400';

    /** @var list<string> Ordre d'affichage : de la catégorie la plus élevée à la plus basse. */
    public const CODES = [
        self::SUP_800,
        self::DE_400_A_799,
        self::INF_400,
    ];

    /** Libellé complet — écrans de gestion et documents PDF. */
    public const LIBELLES = [
        self::SUP_800 => 'Indice ≥ 800',
        self::DE_400_A_799 => 'Indice 400-799',
        self::INF_400 => 'Indice < 400',
    ];

    /**
     * Forme courte de la colonne « Grade/Indice » de l'état de paiement officiel.
     * Elle est figée : les états déjà signés portent ces chaînes dans leur
     * instantané `lignes` et doivent rester imprimables à l'identique.
     */
    public const PLAGES = [
        self::SUP_800 => '≥ 800',
        self::DE_400_A_799 => '400-799',
        self::INF_400 => '< 400',
    ];

    /**
     * Vocabulaire hérité (avant le passage à l'indice nominatif), accepté en
     * entrée mais jamais réémis.
     */
    public const ANCIENS_CODES = [
        'ORDRE_1' => self::SUP_800,
        'ORDRE_2' => self::DE_400_A_799,
        'ORDRE_3' => self::INF_400,
    ];

    // ── Seuils officiels ──
    public const SEUIL_INDICE_SUP = 800;

    public const SEUIL_INDICE_MEDIAN = 400;

    public const SEUIL_SALAIRE_SUP = 372000;

    public const SEUIL_SALAIRE_MEDIAN = 186000;

    /**
     * Catégorie déduite de l'indice (prioritaire) ou, à défaut, du salaire brut.
     *
     * Concordance officielle :
     *   INDICE_SUP_800 : indice ≥ 800   | salaire brut ≥ 372 000
     *   INDICE_400_799 : indice 400-799 | salaire brut 186 000-372 000
     *   INDICE_INF_400 : indice < 400   | salaire brut < 186 000
     */
    public static function determiner(?int $indice, ?int $salaireBrut): ?string
    {
        if ($indice !== null) {
            return match (true) {
                $indice >= self::SEUIL_INDICE_SUP => self::SUP_800,
                $indice >= self::SEUIL_INDICE_MEDIAN => self::DE_400_A_799,
                default => self::INF_400,
            };
        }

        if ($salaireBrut !== null) {
            return match (true) {
                $salaireBrut >= self::SEUIL_SALAIRE_SUP => self::SUP_800,
                $salaireBrut >= self::SEUIL_SALAIRE_MEDIAN => self::DE_400_A_799,
                default => self::INF_400,
            };
        }

        return null;
    }

    /**
     * Ramène une valeur d'entrée au vocabulaire courant.
     *
     * Rend le code tel quel s'il est déjà valide, sa traduction s'il s'agit
     * d'un ancien code ORDRE_*, et null s'il est inconnu — à charge de
     * l'appelant de laisser alors la validation produire son message d'erreur.
     */
    public static function normaliser(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = strtoupper(trim($code));

        if (in_array($code, self::CODES, true)) {
            return $code;
        }

        return self::ANCIENS_CODES[$code] ?? null;
    }

    /** Libellé complet d'un code, ou le code brut s'il est inconnu (données héritées). */
    public static function libelle(?string $code): string
    {
        return self::LIBELLES[$code] ?? (string) $code;
    }

    /**
     * Plage d'indice imprimée sur l'état officiel ; à défaut de code connu, on
     * retombe sur l'indice réel de l'agent, puis sur un tiret cadratin.
     */
    public static function plage(?string $code, ?int $indice = null): string
    {
        return self::PLAGES[$code]
            ?? ($indice !== null ? (string) $indice : '—');
    }

    /** Valeurs acceptées par la validation : vocabulaire courant + héritage. */
    public static function valeursAcceptees(): array
    {
        return [...self::CODES, ...array_keys(self::ANCIENS_CODES)];
    }

    /** Fragment de règle Laravel, p. ex. « in:INDICE_SUP_800,… ». */
    public static function regleIn(): string
    {
        return 'in:'.implode(',', self::valeursAcceptees());
    }
}
