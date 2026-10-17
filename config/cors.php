<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS de Meriz API
    |--------------------------------------------------------------------------
    |
    | Seules les origines du front Meriz peuvent appeler l'API depuis un
    | navigateur ou une webview. L'authentification passe par un Bearer
    | token dans l'entête Authorization, jamais par cookie : on n'envoie
    | donc pas de credentials.
    |
    */

    // broadcasting/auth : le front y autorise ses canaux websocket, avec son
    // Bearer token, donc il lui faut aussi le feu vert CORS.
    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    // Les origines viennent de l'environnement, séparées par des virgules :
    // la liste change d'un déploiement à l'autre, le code non.
    //
    // En production, y mettre le domaine web ET les origines de
    // l'application Tauri, qui ne sont pas des adresses http ordinaires :
    //
    //   CORS_ALLOWED_ORIGINS=https://meriz.fr,https://www.meriz.fr,
    //     tauri://localhost,http://tauri.localhost,https://tauri.localhost
    //
    // Le défaut couvre le développement : le serveur Vite du front.
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:5173,http://127.0.0.1:5173',
        )),
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
