<?php

/*
|--------------------------------------------------------------------------
| L'application Meriz
|--------------------------------------------------------------------------
|
| Le serveur ne rend aucune page : quand un lien envoyé par email a été
| validé, il renvoie la personne vers l'application. Cette adresse est un
| réglage, jamais en dur, car elle change entre le développement, la
| production et une préproduction.
|
*/

return [

    'url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    // La page qui annonce le résultat de la confirmation d'adresse. Elle
    // reçoit le résultat dans le paramètre verification.
    'email_verified_path' => env('FRONTEND_EMAIL_VERIFIED_PATH', '/email-verifie'),

    // La page où l'on choisit un nouveau mot de passe. Contrairement au lien
    // de vérification, qui passe d'abord par l'API, celui-ci mène droit à
    // l'application : elle reçoit token et email dans l'adresse, puis les
    // renvoie à POST /api/auth/password/reset avec le nouveau mot de passe.
    'password_reset_path' => env('FRONTEND_PASSWORD_RESET_PATH', '/reinitialiser-mot-de-passe'),

];
