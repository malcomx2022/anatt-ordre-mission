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
| SÉCURITÉ : aucun repli "*". En production, si la variable n'est pas définie,
| la liste reste VIDE (fail-closed : aucune origine cross-origin autorisée).
| En dehors de la production, repli pratique sur le SPA Vite local.
*/

$corsOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

if (empty($corsOrigins) && env('APP_ENV') !== 'production') {
    $corsOrigins = ['http://localhost:5173', 'http://127.0.0.1:5173'];
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $corsOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Permet au frontend de lire le nom de fichier lors des téléchargements.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];
