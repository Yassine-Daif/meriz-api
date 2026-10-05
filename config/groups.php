<?php

/*
|--------------------------------------------------------------------------
| Groupes d'élèves
|--------------------------------------------------------------------------
|
| Un groupe est un espace entre élèves, sans prof ni note. Son code suit la
| même forme que celui des classes (voir config/classrooms.php).
|
| Les documents d'un groupe comptent dans le quota du groupe, pas dans le
| quota personnel de ses membres.
|
*/

return [

    'max_per_user' => (int) env('GROUPS_MAX_PER_USER', 50),

    'max_members' => (int) env('GROUPS_MAX_MEMBERS', 30),

    'max_memberships_per_user' => (int) env('GROUPS_MAX_MEMBERSHIPS_PER_USER', 50),

    'max_documents_per_group' => (int) env('GROUPS_MAX_DOCUMENTS', 200),

];
