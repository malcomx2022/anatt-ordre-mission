<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Messagerie;
use App\Support\NotificationContenu;
use App\Support\Parametres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Paramètres système persistés dans storage/app/settings.json.
 *
 * Écriture réservée au SUPER_ADMIN (permission parametres.gerer) via une liste
 * blanche stricte de clés ; le secret SMTP est chiffré au repos et jamais renvoyé
 * (masque « ******** »). Le pourcentage d'avance dispose en plus d'une route de
 * LECTURE publique (tout authentifié) qui pilote le calcul côté UI.
 */
class SettingsController extends Controller
{
    /** Chemin du fichier de réglages sur le disque local. */
    private function chemin(): string
    {
        return storage_path('app/settings.json');
    }

    /** Réglages courants ({} si aucun fichier), lus depuis le disque. */
    private function lire(): array
    {
        $path = $this->chemin();

        return file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }

    /** Masque le secret SMTP avant toute réponse au client. */
    private function masquer(array $settings): array
    {
        if (! empty($settings['smtpPassword'])) {
            $settings['smtpPassword'] = '********';
        }

        return $settings;
    }

    /** Réglages courants (secret SMTP masqué). */
    public function show(): JsonResponse
    {
        // (object) : sans réglage, un tableau PHP vide sortirait en `[]` au lieu de `{}`.
        return $this->success((object) $this->masquer($this->lire()));
    }

    /** Écrit une liste blanche stricte de clés (fusion avec l'existant). */
    public function update(Request $request): JsonResponse
    {
        // Liste blanche stricte : on n'écrit QUE des clés connues (anti-pollution
        // d'écriture arbitraire). Les clés inconnues sont ignorées silencieusement.
        $clesAutorisees = [
            // Paramètres généraux (SettingsView)
            'appName', 'appVersion', 'fiscalYear', 'fiscalYearStartMonth', 'batchSize', 'reconciliationDays',
            'sessionDuration', 'maxLoginAttempts', 'mtnEnabled', 'moovEnabled',
            'forceHttps', 'logConnections', 'maintenanceMode',
            'notifications',
            // Pourcentage de l'avance (le reliquat absorbe l'arrondi).
            'partAvance',
            // Configuration email (EmailConfigView)
            'smtpHost', 'smtpPort', 'smtpUser', 'smtpPassword', 'smtpEncryption',
            'fromAddress', 'fromName',
            // Identités des signataires du circuit, imprimées sur l'état
            // de paiement (l'image de signature passe par sa propre route).
            'controleurNom', 'controleurPrenoms', 'controleurFonction',
            'dafNom', 'dafPrenoms', 'dafFonction',
            'dgNom', 'dgPrenoms', 'dgFonction',
        ];

        $entrant = array_intersect_key($request->all(), array_flip($clesAutorisees));

        // Validation du pourcentage d'avance : entier entre 0 et 100.
        if (array_key_exists('partAvance', $entrant)) {
            $v = $entrant['partAvance'];
            if (! is_int($v) || $v < 0 || $v > 100) {
                throw ValidationException::withMessages([
                    'partAvance' => 'Le pourcentage d\'avance doit être un entier entre 0 et 100.',
                ]);
            }
        }

        // Année fiscale : année valide (2000-2100), stockée en entier.
        if (array_key_exists('fiscalYear', $entrant)) {
            $annee = $this->versEntier($entrant['fiscalYear']);
            if ($annee === null || $annee < 2000 || $annee > 2100) {
                throw ValidationException::withMessages([
                    'fiscalYear' => 'L\'année fiscale doit être une année comprise entre 2000 et 2100.',
                ]);
            }
            $entrant['fiscalYear'] = $annee;
        }

        // Mois de début de l'exercice : 1 (janvier) à 12 (décembre).
        if (array_key_exists('fiscalYearStartMonth', $entrant)) {
            $mois = $this->versEntier($entrant['fiscalYearStartMonth']);
            if ($mois === null || $mois < 1 || $mois > 12) {
                throw ValidationException::withMessages([
                    'fiscalYearStartMonth' => 'Le mois de début d\'exercice doit être compris entre 1 et 12.',
                ]);
            }
            $entrant['fiscalYearStartMonth'] = $mois;
        }

        $this->validerReglages($entrant);

        // Fusion avec l'existant : une sauvegarde partielle (ex. paramètres généraux)
        // ne doit pas effacer les autres clés (ex. configuration email).
        $existant = $this->lire();

        // Secret SMTP : jamais stocké en clair. Si le client renvoie le masque
        // (champ inchangé), on conserve la valeur chiffrée existante ; sinon on
        // chiffre la nouvelle valeur avant écriture sur disque.
        if (array_key_exists('smtpPassword', $entrant)) {
            if (in_array($entrant['smtpPassword'], ['', '********'], true)) {
                unset($entrant['smtpPassword']); // inchangé → ne pas écraser
            } else {
                $entrant['smtpPassword'] = Crypt::encryptString($entrant['smtpPassword']);
            }
        }

        $settings = array_merge($existant, $entrant);

        file_put_contents($this->chemin(), json_encode((object) $settings, JSON_PRETTY_PRINT));

        // Invalidation du cache de lecture du pourcentage d'avance.
        if (array_key_exists('partAvance', $entrant)) {
            Parametres::invalidateCache();
        }

        // Réglages d'exploitation (session, connexion, opérateurs, maintenance…) :
        // relus au plus tard à la requête suivante.
        Parametres::invalidateReglages();

        // Invalidation du cache de l'exercice si l'année ou son mois de début change.
        if (array_key_exists('fiscalYear', $entrant) || array_key_exists('fiscalYearStartMonth', $entrant)) {
            Parametres::invalidateAnneeFiscale();
        }

        // Réponse masquée : ne jamais renvoyer le secret au client.
        return $this->success((object) $this->masquer($settings), 'Parametres enregistres.');
    }

    /**
     * Réglages d'exploitation, appliqués par le serveur (Parametres) : une valeur
     * hors domaine est refusée plutôt qu'enregistrée puis ignorée en silence.
     *
     * @param  array<string,mixed>  $entrant
     */
    private function validerReglages(array &$entrant): void
    {
        foreach (['mtnEnabled', 'moovEnabled', 'forceHttps', 'logConnections', 'maintenanceMode'] as $cle) {
            if (array_key_exists($cle, $entrant) && ! is_bool($entrant[$cle])) {
                throw ValidationException::withMessages([$cle => 'Valeur attendue : vrai ou faux.']);
            }
        }

        $bornes = [
            'maxLoginAttempts' => [Parametres::TENTATIVES_MIN, Parametres::TENTATIVES_MAX, 'Le nombre de tentatives de connexion'],
            'sessionDuration' => [Parametres::SESSION_MIN, Parametres::SESSION_MAX, 'La durée de session (minutes)'],
        ];
        foreach ($bornes as $cle => [$min, $max, $libelle]) {
            if (array_key_exists($cle, $entrant)) {
                $n = $this->versEntier($entrant[$cle]);
                if ($n === null || $n < $min || $n > $max) {
                    throw ValidationException::withMessages([$cle => "{$libelle} doit être un entier entre {$min} et {$max}."]);
                }
                $entrant[$cle] = $n;
            }
        }

        if (array_key_exists('appName', $entrant)) {
            $nom = is_string($entrant['appName']) ? trim($entrant['appName']) : null;
            if ($nom === null || $nom === '' || mb_strlen($nom) > 100) {
                throw ValidationException::withMessages(['appName' => "Le nom de l'application est obligatoire (100 caractères au plus)."]);
            }
            $entrant['appName'] = $nom;
        }

        $this->validerMessagerie($entrant);

        if (array_key_exists('notifications', $entrant)) {
            $liste = $entrant['notifications'];
            $valide = is_array($liste) && array_is_list($liste);
            foreach ($valide ? $liste : [] as $n) {
                $valide = $valide && is_array($n) && in_array($n['key'] ?? null, NotificationContenu::TYPES, true)
                    && is_bool($n['enabled'] ?? null);
            }
            if (! $valide) {
                throw ValidationException::withMessages(['notifications' => 'Liste de notifications invalide (type inconnu ou état non booléen).']);
            }
        }
    }

    /**
     * Configuration de l'écran Messagerie, appliquée à l'envoi (App\Support\Messagerie).
     * Un serveur ou un expéditeur vide est accepté : il rend la main au .env.
     *
     * @param  array<string,mixed>  $entrant
     */
    private function validerMessagerie(array &$entrant): void
    {
        $textes = [
            'smtpHost' => [255, 'Le serveur SMTP'],
            'smtpUser' => [255, "Le nom d'utilisateur SMTP"],
            'fromAddress' => [255, "L'adresse d'expéditeur"],
            'fromName' => [100, "Le nom d'expéditeur"],
        ];
        foreach ($textes as $cle => [$max, $libelle]) {
            if (! array_key_exists($cle, $entrant)) {
                continue;
            }
            $valeur = $entrant[$cle] ?? '';
            if (! is_string($valeur) || mb_strlen(trim($valeur)) > $max) {
                throw ValidationException::withMessages([$cle => "{$libelle} doit être un texte de {$max} caractères au plus."]);
            }
            $entrant[$cle] = trim($valeur);
        }

        // Nom d'hôte ou adresse IP : lettres, chiffres, points, tirets (pas d'espace ni de schéma).
        if (($entrant['smtpHost'] ?? '') !== '' && ! preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $entrant['smtpHost'])) {
            throw ValidationException::withMessages(['smtpHost' => 'Le serveur SMTP doit être un nom d\'hôte ou une adresse IP (ex. mail.anatt.bj).']);
        }

        if (($entrant['fromAddress'] ?? '') !== '' && filter_var($entrant['fromAddress'], FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['fromAddress' => "L'adresse d'expéditeur doit être une adresse e-mail valide."]);
        }

        if (array_key_exists('smtpPort', $entrant)) {
            $port = $this->versEntier($entrant['smtpPort']);
            if ($port === null || $port < 1 || $port > 65535) {
                throw ValidationException::withMessages(['smtpPort' => 'Le port SMTP doit être un entier entre 1 et 65535.']);
            }
            $entrant['smtpPort'] = $port;
        }

        if (array_key_exists('smtpEncryption', $entrant) && ! in_array($entrant['smtpEncryption'], Parametres::CHIFFREMENTS_SMTP, true)) {
            throw ValidationException::withMessages(['smtpEncryption' => 'Chiffrement attendu : TLS, SSL ou Aucun.']);
        }
    }

    /**
     * Envoie un e-mail de test au super administrateur connecté, avec la
     * configuration ENREGISTRÉE (serveur de Messagerie, sinon le .env). Un échec
     * est rendu en 422 avec le message du serveur, au lieu d'être découvert au
     * premier e-mail perdu du circuit.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $destinataire = (string) $request->user()->email;
        $sujet = '['.Parametres::nomApplication().'] Test de la configuration e-mail';

        Messagerie::appliquer();

        try {
            Mail::raw(
                "Ce message confirme que la configuration e-mail de l'application fonctionne.\n"
                .'Les notifications du circuit de validation partiront de la même façon.',
                fn ($message) => $message->to($destinataire)->subject($sujet),
            );
        } catch (\Throwable $e) {
            Log::warning('Messagerie : échec de l\'e-mail de test - '.$e->getMessage());

            throw ValidationException::withMessages([
                'smtpHost' => "Échec de l'envoi : ".Str::limit($e->getMessage(), 300),
            ]);
        }

        return $this->success([
            'destinataire' => $destinataire,
            'transport' => Messagerie::transport(),
        ], "E-mail de test envoyé à {$destinataire}.");
    }

    /**
     * Réglages publics utiles à tous les écrans (tout authentifié) : nom de
     * l'application (onglet du navigateur) et opérateurs ouverts à la génération.
     */
    public function application(): JsonResponse
    {
        return $this->success([
            'nom' => Parametres::nomApplication(),
            'operateurs' => [
                'MTN' => Parametres::operateurActif('MTN'),
                'MOOV' => Parametres::operateurActif('MOOV'),
            ],
        ]);
    }

    /** Convertit un entier ou une chaîne numérique en int ; null sinon. */
    private function versEntier(mixed $valeur): ?int
    {
        if (is_int($valeur)) {
            return $valeur;
        }

        return is_string($valeur) && ctype_digit($valeur) ? (int) $valeur : null;
    }

    /**
     * Pourcentage de l'avance (lecture publique, tout authentifié).
     * L'UI la lit pour tous les profils ; l'écriture reste réservée au SUPER_ADMIN
     * via update().
     */
    public function partAvance(): JsonResponse
    {
        return $this->success(Parametres::partAvance());
    }

    /**
     * Exercice courant (lecture publique, tout authentifié) : année déterminée,
     * mois de début et libellé. L'écriture reste réservée au SUPER_ADMIN via update().
     */
    public function anneeFiscale(): JsonResponse
    {
        return $this->success([
            'annee' => Parametres::anneeFiscale(),
            'mois_debut' => Parametres::moisDebutExercice(),
            'libelle' => Parametres::libelleAnneeFiscale(),
        ]);
    }
}
