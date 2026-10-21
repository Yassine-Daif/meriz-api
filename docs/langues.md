# Langue du client public

L’application envoie `Accept-Language: fr` ou `en`. Le middleware
`RequestLanguage` négocie ces deux langues, avec un repli français. Il ne
s’applique qu’à l’API et à l’autorisation des canaux, pas au panneau admin.

Les messages système et les erreurs de validation sont traduits. Les données
des utilisateurs, les clés JSON, les rôles et les autorisations restent identiques.
Les réponses indiquent `Content-Language` et varient sur `Accept-Language`.

Les notifications de vérification et de réinitialisation conservent la langue
de la requête qui les déclenche, y compris si elles sont envoyées plus tard.
Le lien de réinitialisation anglais contient `lang=en`. Le lien signé de
vérification conserve sa signature et ses paramètres existants.

Les traductions sont dans `lang/en.json` et `lang/en/validation.php`.
`tests/Feature/RequestLanguageTest.php` vérifie les messages, les validations,
les notifications et la conservation des contenus et des objets JSON.
