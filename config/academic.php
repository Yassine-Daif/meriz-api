<?php

/*
|--------------------------------------------------------------------------
| Domaines email scolaires ou universitaires
|--------------------------------------------------------------------------
|
| Un compte est marqué scolaire si le domaine de son email correspond à
| l'un de ces motifs. Règles :
|
| - Un motif couvre le domaine lui-même et tous ses sous-domaines.
|   "univ-lyon1.fr" couvre "univ-lyon1.fr" et "etu.univ-lyon1.fr".
| - Le joker * remplace un seul morceau de domaine, sans point.
|   "univ-*.fr" couvre "univ-lyon1.fr", pas "univ-x.com.fr".
|
| Pour compléter la liste sans toucher au code, utilise la variable
| ACADEMIC_EXTRA_DOMAINS du .env, motifs séparés par des virgules.
|
*/

return [

    'domains' => [
        // International
        'edu',
        'edu.au',
        'edu.br',
        'edu.cn',
        'edu.mx',
        'edu.sg',
        'edu.tr',
        'ac.at',
        'ac.il',
        'ac.in',
        'ac.jp',
        'ac.kr',
        'ac.nz',
        'ac.uk',
        'ac.za',

        // France : académies, universités, écoles et organismes
        'ac-*.fr',
        'education.gouv.fr',
        'univ-*.fr',
        'u-*.fr',
        'iut-*.fr',
        'insa-*.fr',
        'sorbonne-universite.fr',
        'universite-paris-saclay.fr',
        'polytechnique.fr',
        'ens.fr',
        'ens-lyon.fr',
        'cnrs.fr',
        'inria.fr',

        // Compléments du .env
        ...array_filter(array_map(
            'trim',
            explode(',', (string) env('ACADEMIC_EXTRA_DOMAINS', '')),
        )),
    ],

];
