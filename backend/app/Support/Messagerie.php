<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/**
 * Applique la configuration de l'écran Messagerie (Paramètres › Messagerie) au
 * mailer, juste avant un envoi.
 *
 * Serveur SMTP renseigné : il remplace le transport du .env (MAIL_MAILER / MAIL_*) ;
 * expéditeur renseigné : il remplace MAIL_FROM_*. Rien de réglé : la configuration
 * d'origine du .env s'applique, comme avant le 28/10/2026.
 *
 * Appelée à chaque envoi (et non au démarrage) : un worker de file d'attente
 * démarré avant un changement de réglage l'applique dès l'e-mail suivant.
 */
final class Messagerie
{
    /** Délai de connexion au serveur SMTP réglé, en secondes. */
    private const DELAI = 10;

    /** Configuration mail d'origine (.env), mémorisée au premier appel. */
    private static ?array $origine = null;

    public static function appliquer(): void
    {
        self::$origine ??= [
            'default' => config('mail.default'),
            'smtp' => (array) config('mail.mailers.smtp'),
            'from' => (array) config('mail.from'),
        ];

        $default = self::$origine['default'];
        $smtp = self::$origine['smtp'];

        $reglee = Parametres::smtp();
        if ($reglee !== null) {
            $default = 'smtp';
            $smtp = array_merge($smtp, ['timeout' => self::DELAI], $reglee);
        }

        config([
            'mail.default' => $default,
            'mail.mailers.smtp' => $smtp,
            'mail.from' => array_merge(self::$origine['from'], Parametres::expediteur()),
        ]);

        // Les mailers déjà construits portent l'ancienne configuration.
        Mail::purge('smtp');
        Mail::purge(self::$origine['default']);
    }

    /** Transport effectivement utilisé à l'envoi (après appliquer()). */
    public static function transport(): string
    {
        return (string) config('mail.default');
    }
}
