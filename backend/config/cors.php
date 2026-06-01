<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
| Le SPA (domaine principal) appelle l'API (sous-domaine api.) : requêtes
| cross-origin. L'authentification se fait par token Bearer (pas de cookie),
| donc supports_credentials reste à false et on peut restreindre les origines.
|
| CORS_ALLOWED_ORIGINS : liste séparée par des virgules (ex. https://anatt.tld).
| Valeur par défaut "*" en l'absence de configuration.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Permet au frontend de lire le nom de fichier lors des téléchargements.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];
