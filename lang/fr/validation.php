<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messages de validation
    |--------------------------------------------------------------------------
    |
    | Messages par défaut du validateur, en français. Les messages écrits à
    | la main restent là où ils sont : dans les Form Requests, les règles de
    | App\Rules et les actions. Ce fichier ne couvre que ce que Laravel dit
    | tout seul.
    |
    | Le nom lisible de chaque champ est dans le tableau attributes, en bas.
    |
    */

    'accepted' => 'Le champ :attribute doit être accepté.',
    'accepted_if' => 'Le champ :attribute doit être accepté quand :other vaut :value.',
    'active_url' => 'Le champ :attribute doit être une adresse web valide.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date égale ou postérieure au :date.',
    'alpha' => 'Le champ :attribute ne peut contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne peut contenir que des lettres, des chiffres, des tirets et des tirets bas.',
    'alpha_num' => 'Le champ :attribute ne peut contenir que des lettres et des chiffres.',
    'any_of' => 'Le champ :attribute est invalide.',
    'array' => 'Le champ :attribute doit être une liste.',
    'array_keys' => 'Le champ :attribute ne peut contenir que les clés suivantes : :values.',
    'ascii' => 'Le champ :attribute ne peut contenir que des caractères simples, sans accent.',
    'base64' => 'Le champ :attribute doit être encodé en Base64.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date égale ou antérieure au :date.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max kilooctets.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit valoir vrai ou faux.',
    'can' => 'Le champ :attribute contient une valeur non autorisée.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'contains' => 'Il manque une valeur obligatoire au champ :attribute.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'date_equals' => 'Le champ :attribute doit être la date du :date.',
    'date_format' => 'Le champ :attribute doit respecter le format :format.',
    'decimal' => 'Le champ :attribute doit avoir :decimal chiffres après la virgule.',
    'declined' => 'Le champ :attribute doit être refusé.',
    'declined_if' => 'Le champ :attribute doit être refusé quand :other vaut :value.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'dimensions' => 'Les dimensions de l\'image :attribute ne sont pas valides.',
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'doesnt_contain' => 'Le champ :attribute ne doit contenir aucune des valeurs suivantes : :values.',
    'doesnt_end_with' => 'Le champ :attribute ne doit pas finir par une des valeurs suivantes : :values.',
    'doesnt_start_with' => 'Le champ :attribute ne doit pas commencer par une des valeurs suivantes : :values.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'encoding' => 'Le champ :attribute doit être encodé en :encoding.',
    'ends_with' => 'Le champ :attribute doit finir par une des valeurs suivantes : :values.',
    'enum' => 'La valeur du champ :attribute n\'est pas autorisée.',
    'exists' => 'La valeur du champ :attribute n\'existe pas.',
    'extensions' => 'Le fichier :attribute doit avoir une des extensions suivantes : :values.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute ne peut pas être vide.',
    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser plus de :value kilooctets.',
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'array' => 'Le champ :attribute doit contenir au moins :value éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :value kilooctets.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caractères.',
    ],
    'hex_color' => 'Le champ :attribute doit être une couleur hexadécimale valide.',
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur du champ :attribute n\'est pas autorisée.',
    'in_array' => 'Le champ :attribute doit exister dans :other.',
    'in_array_keys' => 'Le champ :attribute doit contenir au moins une des clés suivantes : :values.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'ip' => 'Le champ :attribute doit être une adresse IP valide.',
    'ipv4' => 'Le champ :attribute doit être une adresse IPv4 valide.',
    'ipv6' => 'Le champ :attribute doit être une adresse IPv6 valide.',
    'json' => 'Le champ :attribute doit être du JSON valide.',
    'list' => 'Le champ :attribute doit être une liste.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'lt' => [
        'array' => 'Le champ :attribute doit contenir moins de :value éléments.',
        'file' => 'Le fichier :attribute doit peser moins de :value kilooctets.',
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caractères.',
    ],
    'lte' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser au plus :value kilooctets.',
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au plus :value caractères.',
    ],
    'mac_address' => 'Le champ :attribute doit être une adresse MAC valide.',
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne peut pas peser plus de :max kilooctets.',
        'numeric' => 'Le champ :attribute ne peut pas être supérieur à :max.',
        'string' => 'Le champ :attribute ne peut pas contenir plus de :max caractères.',
    ],
    'max_digits' => 'Le champ :attribute ne peut pas contenir plus de :max chiffres.',
    'mimes' => 'Le fichier :attribute doit être d\'un des types suivants : :values.',
    'mimetypes' => 'Le fichier :attribute doit être d\'un des types suivants : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min kilooctets.',
        'numeric' => 'Le champ :attribute doit être au moins égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'min_digits' => 'Le champ :attribute doit contenir au moins :min chiffres.',
    'missing' => 'Le champ :attribute ne doit pas être envoyé.',
    'missing_if' => 'Le champ :attribute ne doit pas être envoyé quand :other vaut :value.',
    'missing_unless' => 'Le champ :attribute ne doit pas être envoyé, sauf si :other vaut :value.',
    'missing_with' => 'Le champ :attribute ne doit pas être envoyé avec :values.',
    'missing_with_all' => 'Le champ :attribute ne doit pas être envoyé avec :values.',
    'multiple_of' => 'Le champ :attribute doit être un multiple de :value.',
    'not_in' => 'La valeur du champ :attribute n\'est pas autorisée.',
    'not_regex' => 'Le format du champ :attribute n\'est pas valide.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisis-en un autre.',
    ],
    'present' => 'Le champ :attribute doit être envoyé.',
    'present_if' => 'Le champ :attribute doit être envoyé quand :other vaut :value.',
    'present_unless' => 'Le champ :attribute doit être envoyé, sauf si :other vaut :value.',
    'present_with' => 'Le champ :attribute doit être envoyé avec :values.',
    'present_with_all' => 'Le champ :attribute doit être envoyé avec :values.',
    'prohibited' => 'Le champ :attribute n\'est pas autorisé.',
    'prohibited_if' => 'Le champ :attribute n\'est pas autorisé quand :other vaut :value.',
    'prohibited_if_accepted' => 'Le champ :attribute n\'est pas autorisé quand :other est accepté.',
    'prohibited_if_declined' => 'Le champ :attribute n\'est pas autorisé quand :other est refusé.',
    'prohibited_unless' => 'Le champ :attribute n\'est pas autorisé, sauf si :other est parmi :values.',
    'prohibits' => 'Le champ :attribute interdit l\'envoi de :other.',
    'regex' => 'Le format du champ :attribute n\'est pas valide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_array_keys' => 'Le champ :attribute doit contenir des entrées pour : :values.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_if_accepted' => 'Le champ :attribute est obligatoire quand :other est accepté.',
    'required_if_declined' => 'Le champ :attribute est obligatoire quand :other est refusé.',
    'required_unless' => 'Le champ :attribute est obligatoire, sauf si :other est parmi :values.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est envoyé.',
    'required_with_all' => 'Le champ :attribute est obligatoire quand :values sont envoyés.',
    'required_without' => 'Le champ :attribute est obligatoire quand :values n\'est pas envoyé.',
    'required_without_all' => 'Le champ :attribute est obligatoire quand aucun de :values n\'est envoyé.',
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit peser :size kilooctets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'starts_with' => 'Le champ :attribute doit commencer par une des valeurs suivantes : :values.',
    'string' => 'Le champ :attribute doit être du texte.',
    'timezone' => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',
    'uploaded' => 'L\'envoi du fichier :attribute a échoué.',
    'uppercase' => 'Le champ :attribute doit être en majuscules.',
    'url' => 'Le champ :attribute doit être une adresse web valide.',
    'ulid' => 'Le champ :attribute doit être un identifiant valide.',
    'uuid' => 'Le champ :attribute doit être un identifiant valide.',

    /*
    |--------------------------------------------------------------------------
    | Messages par champ
    |--------------------------------------------------------------------------
    |
    | Pour un message propre à un couple champ + règle. On préfère les garder
    | près de leur Form Request, donc ce tableau reste vide.
    |
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Noms lisibles des champs
    |--------------------------------------------------------------------------
    |
    | Remplace le nom technique dans les messages : « Le champ mot de passe
    | est obligatoire. » plutôt que « Le champ password ».
    |
    */

    'attributes' => [
        // Compte
        'name' => 'nom',
        'first_name' => 'prénom',
        'email' => 'adresse email',
        'password' => 'mot de passe',
        'device_name' => 'nom de l\'appareil',
        'bio' => 'présentation',
        'bio_shared' => 'partage de la présentation',
        'contact' => 'contact',
        'contact_shared' => 'partage du contact',
        'avatar_bg' => 'couleur de fond',
        'avatar_fg' => 'couleur du texte',

        // Classes et groupes
        'code' => 'code',
        'role' => 'rôle',
        'classroom' => 'classe',

        // Documents et travaux
        'content' => 'contenu',
        'title' => 'titre',

        // Devoirs
        'instructions' => 'consigne',
        'type' => 'type',
        'due_at' => 'date limite',
        'base_content' => 'base de départ',
        'solution_content' => 'corrigé',
        'image' => 'image',

        // Rendus
        'grade' => 'note',
        'feedback' => 'retour',

        // Cours et médias
        'blocks' => 'blocs',
        'file' => 'fichier',

        // Commentaires
        'body' => 'texte',
        'position_x' => 'abscisse',
        'position_y' => 'ordonnée',

        // Pagination
        'page' => 'page',
        'per_page' => 'nombre par page',
    ],

];
