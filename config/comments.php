<?php

/*
|--------------------------------------------------------------------------
| Commentaires
|--------------------------------------------------------------------------
|
| Commentaires persistés sur un travail : généraux, ou posés à un endroit
| du schéma. Le crayon rouge éphémère, lui, vit côté application.
|
| La position est bornée pour éviter l'abus, mais son sens appartient à
| l'application : le serveur ne l'interprète pas.
|
*/

return [

    'max_body_chars' => (int) env('COMMENTS_MAX_BODY_CHARS', 2000),

    'max_per_document' => (int) env('COMMENTS_MAX_PER_DOCUMENT', 500),

    'position_bound' => 1_000_000,

];
