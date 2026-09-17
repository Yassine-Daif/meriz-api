<?php

/*
|--------------------------------------------------------------------------
| Documents
|--------------------------------------------------------------------------
|
| Limites contre l'abus. La taille se compte en octets. Garde-la sous le
| post_max_size de PHP (8 Mo par défaut), sinon PHP rejette la requête
| avant Laravel.
|
*/

return [

    'max_content_bytes' => (int) env('DOCUMENTS_MAX_CONTENT_BYTES', 2 * 1024 * 1024),

    'max_per_user' => (int) env('DOCUMENTS_MAX_PER_USER', 1000),

    'per_page' => 50,

    'max_per_page' => 100,

];
