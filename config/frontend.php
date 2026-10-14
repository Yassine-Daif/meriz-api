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

];
