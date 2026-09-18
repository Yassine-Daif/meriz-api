<?php

/*
|--------------------------------------------------------------------------
| Classes
|--------------------------------------------------------------------------
|
| Le code de classe sert à rejoindre. L'alphabet exclut les caractères
| ambigus (0/O, 1/I/L). Avec 8 caractères parmi 31, il y a environ
| 8,5 × 10^11 codes possibles : la limite de débit rend la devinette vaine.
|
*/

return [

    'code_length' => 8,

    'code_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',

    'max_per_teacher' => (int) env('CLASSROOMS_MAX_PER_TEACHER', 100),

    'max_members' => (int) env('CLASSROOMS_MAX_MEMBERS', 200),

    'max_memberships_per_user' => (int) env('CLASSROOMS_MAX_MEMBERSHIPS_PER_USER', 100),

];
