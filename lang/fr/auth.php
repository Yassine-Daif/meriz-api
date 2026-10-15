<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messages d'authentification
    |--------------------------------------------------------------------------
    |
    | Aucun de ces messages ne sort aujourd'hui : la connexion passe par
    | AuthenticateUser, qui répond toujours la même chose que l'email soit
    | inconnu ou le mot de passe faux. Ils sont là pour que rien ne retombe
    | en anglais si une route les utilise un jour.
    |
    */

    'failed' => 'Email ou mot de passe incorrect.',
    'password' => 'Mot de passe incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessaie dans :seconds secondes.',

];
