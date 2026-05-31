<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paramètres des fichiers de paiement Mobile Money
    |--------------------------------------------------------------------------
    */

    // Indicatif pays préfixé au téléphone national (10 chiffres, 0 initial conservé).
    'indicatif' => env('PAIEMENT_INDICATIF', '229'),

    // Au-delà de ce nombre de bénéficiaires, la génération bascule en file (job Redis).
    'async_seuil' => (int) env('PAIEMENT_ASYNC_SEUIL', 50),

    'mtn' => [
        // Compte émetteur figurant dans l'en-tête HDR du fichier .txt.
        'compte' => env('MTN_COMPTE', 'SARALY'),
    ],

    'moov' => [
        // Feuille du classeur .xlsx.
        'sheet' => 'Sheet1',
    ],

];
