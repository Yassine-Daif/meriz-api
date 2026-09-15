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

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Développement : serveur Vite du front.
        'http://localhost:5173',
        'http://127.0.0.1:5173',

        // À REMPLIR : domaine web de production.
        // 'https://app.meriz.example',

        // À REMPLIR : application Tauri (v2).
        // 'tauri://localhost',       // macOS et Linux
        // 'http://tauri.localhost',  // Windows
        // 'https://tauri.localhost', // Windows, schéma https
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
