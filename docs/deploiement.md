# Checklist de déploiement

À dérouler à la mise en ligne. **Rien de tout cela n'est réglé dans le code** :
ce sont des points de configuration, d'environnement et de serveur. Les
constats de code sont dans [securite.md](securite.md), et les détails d'envoi
d'emails dans [email.md](email.md), de websocket dans [websocket.md](websocket.md).

## Environnement de l'application

- [ ] **`APP_ENV=production` et `APP_DEBUG=false`.** En debug, toute erreur
      autre qu'un 404 renvoie la trace d'exception et les chemins du serveur.
      C'est le point le plus important de cette liste.
- [ ] **`APP_KEY` généré sur le serveur** (`php artisan key:generate`), jamais
      repris du développement. Il chiffre et il signe.
- [ ] **`APP_URL` = l'adresse publique de l'API**, en `https`. Les liens de
      vérification d'email sont signés à partir de cette valeur : si elle est
      fausse, toutes les signatures sont refusées et personne ne peut confirmer
      son adresse.
- [ ] **`FRONTEND_URL` = l'adresse de l'application**, et
      `FRONTEND_EMAIL_VERIFIED_PATH` = la page de retour qu'elle expose.
- [ ] **`SANCTUM_TOKEN_EXPIRATION`** laissé par défaut, ou une valeur positive
      en minutes. Une valeur vide ou nulle retombe sur 30 jours.

## Accès depuis l'application

- [ ] **`config/cors.php`** : décommenter le domaine web de production et les
      origines Tauri (`tauri://localhost`, `http://tauri.localhost`,
      `https://tauri.localhost`). Sans ça, l'application ne peut pas appeler
      l'API depuis un navigateur ni depuis la webview.
- [ ] Vérifier que `supports_credentials` reste à `false` : l'authentification
      est par jeton, jamais par cookie.

## Emails

- [ ] **Un vrai service de mail** : `MAIL_MAILER=smtp`, `ses`, `postmark` ou
      `resend`, avec ses identifiants. En `log`, rien ne part.
- [ ] `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME` renseignés, sur un domaine dont
      on contrôle les enregistrements SPF et DKIM, sinon les liens de
      vérification finiront en indésirables.

## Websocket

- [ ] **`REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_APP_ID` neufs**, jamais
      ceux du développement.
- [ ] **`allowed_origins` restreint** dans `config/reverb.php` : il est à `['*']`
      par défaut.
- [ ] Le service Reverb sous un gestionnaire de processus, et exposé en `wss`
      derrière le serveur web. Procédure détaillée dans
      [websocket.md](websocket.md).

## Serveur et base

- [ ] **HTTPS obligatoire**, redirection 80 → 443, certificat renouvelé
      automatiquement. Le jeton voyage dans l'entête `Authorization` : en clair,
      il est lisible sur le réseau.
- [ ] **Base de production** MySQL ou PostgreSQL, avec un compte dédié aux seuls
      droits nécessaires. Pas de SQLite en production.
- [ ] **`storage/` et `bootstrap/cache/`** accessibles en écriture au seul
      utilisateur du serveur web.
- [ ] **`storage/app/private` hors de la racine web.** Les médias ne doivent
      être servis que par l'API, qui vérifie les droits ; un accès direct par le
      serveur web contournerait toutes les Policies.
- [ ] **`upload_max_filesize` et `post_max_size` à 16 Mo au moins**, cohérents
      avec `LESSONS_MAX_AUDIO_KB`, sinon les dépôts d'audio échouent avant
      d'arriver à Laravel.
- [ ] Un plafond de taille de requête côté serveur web (`client_max_body_size`
      pour nginx), un peu au-dessus de la limite PHP.

## Mise en route

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache route:cache event:cache`
- [ ] **Planificateur actif** : `schedule:run` chaque minute par cron. C'est lui
      qui purge les jetons expirés.
- [ ] `php artisan about` relu une fois en ligne : debug coupé, caches en place,
      bon pilote de base.

## Après la mise en ligne

- [ ] **Sauvegardes** de la base et de `storage/app/private`, et une
      restauration réellement testée. Ce sont des travaux d'élèves.
- [ ] **`composer audit`** dans la chaîne de déploiement, pour être prévenu
      d'une alerte sur une dépendance.
- [ ] Surveillance des 429 et des 500 : un pic de 429 sur la connexion ou sur
      les codes de classe veut dire qu'on essaie de les deviner.
- [ ] Les comptes de démonstration (`meriz:demo-data`) ne doivent **jamais**
      exister en production : la commande refuse déjà de s'exécuter hors
      développement sans `--force`.
