<?php

/*
|--------------------------------------------------------------------------
| Devoirs
|--------------------------------------------------------------------------
|
| L'image d'un devoir est le seul fichier que le serveur accepte. Elle est
| stockée sur le disque privé et servie seulement aux membres de la classe.
| Garde max_image_kb sous l'upload_max_filesize de PHP (2 Mo par défaut),
| sinon PHP rejette le fichier avant Laravel.
|
*/

return [

    'max_image_kb' => (int) env('ASSIGNMENTS_MAX_IMAGE_KB', 2048),

    // Types acceptés, vérifiés sur le contenu réel du fichier. Pas de SVG,
    // qui peut porter du script.
    'image_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    'image_disk' => 'local',

    'max_per_classroom' => (int) env('ASSIGNMENTS_MAX_PER_CLASSROOM', 500),

    'max_instructions_chars' => 20000,

];
