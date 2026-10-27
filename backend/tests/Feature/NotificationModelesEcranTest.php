<?php

use App\Support\NotificationContenu;

/*
 * L'écran Configuration e-mail (frontend/src/views/admin/EmailConfigView.vue)
 * présente les notifications à titre de référence. Il avait dérivé (modèles de
 * l'ancien circuit) : chaque type produit par NotificationContenu doit y figurer,
 * avec le même sujet.
 */
it('présente chaque notification réelle, avec son sujet, sur l’écran Configuration e-mail', function () {
    $ecran = file_get_contents(base_path('../frontend/src/views/admin/EmailConfigView.vue'));
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

    // Rappel : le sujet réel reste celui de NotificationContenu.
    expect(NotificationContenu::sujet('om_rejete', ['reference_semo' => 'X']))->toBe('OM X rejeté');
});
