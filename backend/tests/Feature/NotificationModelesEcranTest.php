<?php

use App\Support\NotificationContenu;

/*
 * Les écrans Configuration e-mail et Paramètres › Notifications présentent les
 * notifications à titre de référence, depuis une source unique
 * (frontend/src/utils/notifications.ts). Ils avaient dérivé (modèles de l'ancien
 * circuit) : chaque type produit par NotificationContenu doit y figurer, avec le
 * même sujet, et les deux écrans doivent lire cette source.
 */
it('présente chaque notification réelle, avec son sujet, sur l’écran Configuration e-mail', function () {
    $ecran = file_get_contents(base_path('../frontend/src/utils/notifications.ts'));
    $source = file_get_contents(app_path('Support/NotificationContenu.php'));

    preg_match('/function sujet.*?match \(\$type\) \{(.*?)default/s', $source, $bloc);
    preg_match_all("/'([a-z_]+)' => \"([^\"]+)\"/", $bloc[1], $types, PREG_SET_ORDER);
    expect($types)->toHaveCount(7);

    foreach ($types as [, $type, $sujet]) {
        // {$data['numero']} côté PHP ↔ {numero} côté écran.
        $attendu = preg_replace("/\{\\\$data\['([a-z_]+)'\]\}/", '{$1}', $sujet);
        expect($ecran)->toContain("type: '{$type}'")
            ->and($ecran)->toContain("subject: '{$attendu}'");
    }

    foreach (['EmailConfigView', 'SettingsView'] as $vue) {
        expect(file_get_contents(base_path("../frontend/src/views/admin/{$vue}.vue")))
            ->toContain('NOTIFICATIONS_CIRCUIT');
    }

    // Rappel : le sujet réel reste celui de NotificationContenu.
    expect(NotificationContenu::sujet('om_rejete', ['reference_semo' => 'X']))->toBe('OM X rejeté');
});
