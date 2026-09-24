<?php

/*
|--------------------------------------------------------------------------
| Cours
|--------------------------------------------------------------------------
|
| Un cours est une page de blocs ordonnés, conservée telle quelle. Les blocs
| image et audio renvoient à un média envoyé à part, stocké sur le disque
| privé et servi seulement aux membres de la classe. La vidéo passe par un
| lien, jamais par un fichier hébergé.
|
| Les tailles doivent rester sous upload_max_filesize et post_max_size de
| PHP (16 Mo et 20 Mo ici), sinon PHP rejette avant Laravel.
|
*/

return [

    'max_blocks_bytes' => (int) env('LESSONS_MAX_BLOCKS_BYTES', 256 * 1024),

    // Types acceptés, vérifiés sur le contenu réel. Pas de SVG, qui peut
    // porter du script.
    'image_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    'audio_mimes' => [
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/webm' => 'weba',
    ],

    'max_image_kb' => (int) env('LESSONS_MAX_IMAGE_KB', 2048),

    'max_audio_kb' => (int) env('LESSONS_MAX_AUDIO_KB', 16384),

    'max_per_classroom' => (int) env('LESSONS_MAX_PER_CLASSROOM', 200),

    'max_media_per_lesson' => (int) env('LESSONS_MAX_MEDIA_PER_LESSON', 50),

    'disk' => 'local',

];
