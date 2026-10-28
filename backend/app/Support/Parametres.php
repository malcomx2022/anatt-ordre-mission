<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres généraux de l'application, lus depuis settings.json.
 *
 * Les valeurs sont mises en cache 60 secondes pour éviter de lire le
 * fichier à chaque calcul — les paramètres changent rarement et une
 * staleness d'une minute est sans impact.
 */
class Parametres
{
    private const FICHIER = 'settings.json';

    private const CLE = 'partAvance';

    private const VALEUR_PAR_DEFAUT = 70;

    /** Bornes raisonnables pour une année d'exercice. */
    private const ANNEE_MIN = 2000;

    private const ANNEE_MAX = 2100;

    /** Pourcentage de l'avance (le reliquat absorbe l'arrondi). */
    public static function partAvance(): int
    {
        return Cache::remember('parametres.part_avance', 60, fn () => self::lire());
    }

    /** Invalidation du cache du pourcentage d'avance après écriture. */
    public static function invalidateCache(): void
    {
        Cache::forget('parametres.part_avance');
    }

    // ── Exercice / année fiscale ──────────────────────────────────────────────

    /**
     * Mois de début de l'exercice (1 = janvier … 12 = décembre).
     * Par défaut janvier : l'exercice = l'année civile.
     */
    public static function moisDebutExercice(): int
    {
        return Cache::remember('parametres.mois_debut_exercice', 60, fn () => self::lireMoisDebut());
    }

    /**
     * Année fiscale « en cours » : la valeur configurée si elle est valide,
     * sinon celle DÉTERMINÉE à partir de la date du jour et du mois de début.
     */
    public static function anneeFiscale(): int
    {
        return Cache::remember('parametres.annee_fiscale', 60, fn () => self::lireAnnee());
    }

    /**
     * Détermine l'année fiscale à laquelle appartient une date, selon le mois
     * de début d'exercice. L'exercice est étiqueté par son année de DÉBUT :
     * un exercice débutant en juillet et couvrant juil. N → juin N+1 vaut « N ».
     */
    public static function anneeFiscalePour(Carbon $date): int
    {
        $debut = self::moisDebutExercice();

        return $date->month >= $debut ? $date->year : $date->year - 1;
    }

    /**
     * Libellé de l'exercice : « 2026 » si l'exercice = année civile,
     * « 2026-2027 » s'il est à cheval sur deux années civiles.
     */
    public static function libelleAnneeFiscale(?int $annee = null): string
    {
        $annee ??= self::anneeFiscale();

        return self::moisDebutExercice() === 1 ? (string) $annee : $annee.'-'.($annee + 1);
    }

    /** Invalidation du cache de l'exercice après écriture. */
    public static function invalidateAnneeFiscale(): void
    {
        Cache::forget('parametres.annee_fiscale');
        Cache::forget('parametres.mois_debut_exercice');
    }

    // ── Réglages d'exploitation (écran Paramètres) ────────────────────────────
    //
    // Un réglage absent de settings.json garde le comportement d'avant son
    // introduction : rien ne change sur une instance qui ne l'a jamais enregistré.

    /** Bornes des réglages numériques : une valeur hors bornes est refusée à l'écriture. */
    public const TENTATIVES_MIN = 3;

    public const TENTATIVES_MAX = 20;

    public const SESSION_MIN = 5;

    public const SESSION_MAX = 1440;

    /** Nom de l'application : onglet du navigateur et objet des e-mails. */
    public static function nomApplication(): string
    {
        $nom = trim((string) (self::reglages()['appName'] ?? ''));

        return $nom !== '' ? $nom : (string) config('app.name');
    }

    /** L'opérateur (MTN / MOOV) est-il ouvert à la génération des fichiers de paiement ? */
    public static function operateurActif(string $operateur): bool
    {
        $cle = ['MTN' => 'mtnEnabled', 'MOOV' => 'moovEnabled'][strtoupper($operateur)] ?? null;

        return $cle === null || (self::reglages()[$cle] ?? true) !== false;
    }

    /** Tentatives de connexion par minute (par IP, et par e-mail × IP) ; 5 par défaut. */
    public static function tentativesConnexion(): int
    {
        $n = self::versEntier(self::reglages()['maxLoginAttempts'] ?? null);

        return $n !== null && $n >= self::TENTATIVES_MIN && $n <= self::TENTATIVES_MAX ? $n : 5;
    }

    /**
     * Durée de validité d'une session (jeton Sanctum), en minutes après la
     * connexion ; null si non réglée (SANCTUM_EXPIRATION s'applique).
     */
    public static function dureeSession(): ?int
    {
        $n = self::versEntier(self::reglages()['sessionDuration'] ?? null);

        return $n !== null && $n >= self::SESSION_MIN && $n <= self::SESSION_MAX ? $n : null;
    }

    /** Refuser l'API en HTTP (redirection des lectures, 403 pour les écritures). */
    public static function forcerHttps(): bool
    {
        return (self::reglages()['forceHttps'] ?? false) === true;
    }

    /** Tracer aussi les connexions ÉCHOUÉES au journal d'audit (les réussies le sont toujours). */
    public static function journaliserConnexions(): bool
    {
        return (self::reglages()['logConnections'] ?? false) === true;
    }

    /** Mode maintenance : l'API répond 503 à tous, sauf au super administrateur. */
    public static function maintenance(): bool
    {
        return (self::reglages()['maintenanceMode'] ?? false) === true;
    }

    /** Une notification de ce type (cloche + e-mail) est-elle émise ? Oui par défaut. */
    public static function notificationActive(string $type): bool
    {
        foreach ((array) (self::reglages()['notifications'] ?? []) as $n) {
            if (is_array($n) && ($n['key'] ?? null) === $type) {
                return ($n['enabled'] ?? true) !== false;
            }
        }

        return true;
    }

    /** Invalidation du cache des réglages d'exploitation après écriture. */
    public static function invalidateReglages(): void
    {
        Cache::forget('parametres.reglages');
    }

    /** @return array<string,mixed> */
    private static function reglages(): array
    {
        return Cache::remember('parametres.reglages', 60, fn () => self::charger());
    }

    // ── Lecture disque ────────────────────────────────────────────────────────

    private static function lire(): int
    {
        $valeur = self::charger()[self::CLE] ?? self::VALEUR_PAR_DEFAUT;

        return is_int($valeur) && $valeur >= 0 && $valeur <= 100 ? $valeur : self::VALEUR_PAR_DEFAUT;
    }

    private static function lireMoisDebut(): int
    {
        $valeur = self::charger()['fiscalYearStartMonth'] ?? null;
        $mois = self::versEntier($valeur);

        return $mois !== null && $mois >= 1 && $mois <= 12 ? $mois : 1;
    }

    private static function lireAnnee(): int
    {
        $annee = self::versEntier(self::charger()['fiscalYear'] ?? null);

        if ($annee !== null && $annee >= self::ANNEE_MIN && $annee <= self::ANNEE_MAX) {
            return $annee;
        }

        // Aucune année valide configurée : on la DÉTERMINE depuis la date du jour.
        return self::anneeFiscalePour(Carbon::now());
    }

    /** Contenu brut de settings.json ([] si absent). */
    private static function charger(): array
    {
        $chemin = storage_path('app/'.self::FICHIER);

        if (! is_file($chemin)) {
            return [];
        }

        return json_decode((string) file_get_contents($chemin), true) ?: [];
    }

    /** Convertit un entier ou une chaîne numérique en int ; null sinon. */
    private static function versEntier(mixed $valeur): ?int
    {
        if (is_int($valeur)) {
            return $valeur;
        }

        return is_string($valeur) && ctype_digit($valeur) ? (int) $valeur : null;
    }
}
