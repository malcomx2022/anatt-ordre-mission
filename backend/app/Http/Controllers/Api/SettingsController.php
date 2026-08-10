<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Parametres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
        return $this->success($this->masquer($this->lire()));
    }

    /** Écrit une liste blanche stricte de clés (fusion avec l'existant). */
    public function update(Request $request): JsonResponse
    {
        // Liste blanche stricte : on n'écrit QUE des clés connues (anti-pollution
        // d'écriture arbitraire). Les clés inconnues sont ignorées silencieusement.
        $clesAutorisees = [
            // Paramètres généraux (SettingsView)
            'appName', 'appVersion', 'fiscalYear', 'batchSize', 'reconciliationDays',
            'sessionDuration', 'maxLoginAttempts', 'mtnEnabled', 'moovEnabled',
            'doubleValidation', 'forceHttps', 'logConnections', 'maintenanceMode',
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

        file_put_contents($this->chemin(), json_encode($settings, JSON_PRETTY_PRINT));

        // Invalidation du cache de lecture du pourcentage d'avance.
        if (array_key_exists('partAvance', $entrant)) {
            Parametres::invalidateCache();
        }

        // Réponse masquée : ne jamais renvoyer le secret au client.
        return $this->success($this->masquer($settings), 'Parametres enregistres.');
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
}
