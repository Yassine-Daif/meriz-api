# Websocket, observation en direct

Le serveur diffuse le travail d'un élève observé par le prof de son devoir.
Le transport est Laravel Reverb. Ce document explique comment le lancer en
développement, puis comment le déployer sur le VPS.

## Ce qui circule

Deux usages distincts.

### 1. Observation simple, par le serveur

- Un seul type d'évènement, `work.updated`, sur un canal privé par couple
  devoir et élève : `private-assignments.{devoir}.work.{eleve}`.
- L'évènement ne part que si le prof a activé le suivi sur ce devoir.
- L'abonnement au canal est autorisé par `AssignmentPolicy::observeLive`,
  la même règle que la lecture HTTP. Un élève, un autre prof ou un
  non-membre ne peut jamais s'abonner.
- Chaque abonnement, et chaque ping `POST /api/assignments/{devoir}/live/{eleve}/seen`,
  met à jour l'heure de dernière lecture que l'élève voit sur son document.

### 2. Co-édition, de pair à pair

- **Canal de présence par document** : `presence-documents.{document}`.
- **Qui peut rejoindre**, et personne d'autre :
  - l'élève propriétaire du travail ;
  - le prof du devoir lié, **seulement si le suivi est activé**.
  La décision est prise par `DocumentPolicy::collaborate`, qui compose
  `AssignmentPolicy::observeLive`. Un document personnel n'a pas de devoir,
  donc aucun prof n'y entre. Le travail de groupe s'ajoutera dans cette
  seule méthode.
- **La présence porte l'identité d'affichage** : `id`, `name`, `first_name`,
  `role`, `avatar_bg`, `avatar_fg`. Rien d'autre : ni email, ni présentation,
  ni contact, ni note, ni corrigé.
- **Les mises à jour circulent en évènements de client**, sans aucun appel
  HTTP par frappe :
  - `client-yjs-update` : les mises à jour Yjs, opaques pour le serveur ;
  - `client-awareness` : curseurs et sélections.
- **Transparence** : le prof apparaît dans la présence, donc l'élève le voit.
  Son arrivée écrit aussi l'heure de dernière lecture.
- **Persistance** : inchangée. C'est l'élève propriétaire qui enregistre
  l'état par `PATCH /api/documents/{id}`, au format JSON d'aujourd'hui. Le
  serveur ne stocke aucun format Yjs.

### Réglages qui comptent pour la co-édition

Dans `config/reverb.php` :

- `accept_client_events_from` reste à **`members`**. Reverb n'accepte alors un
  `client-*` que d'une connexion déjà membre du canal, et estampille lui-même
  le `user_id` authentifié : personne ne peut se faire passer pour un autre.
  **Ne jamais passer à `all`.**
- `max_message_size` et `max_request_size` sont à **3 Mo**, au-dessus de la
  limite des documents (2 Mo). Les valeurs par défaut (10 Ko) rejetaient la
  diffusion d'un gros instantané.
- La limitation de débit reste désactivée : une session Yjs envoie beaucoup
  de petits messages.

## Développement

Deux processus, dans deux terminaux :

```bash
php artisan serve          # l'API, port 8000
php artisan reverb:start   # le websocket, port 8080
```

Variables dans `.env` :

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=meriz-local
REVERB_APP_KEY=<valeur aléatoire>
REVERB_APP_SECRET=<valeur aléatoire>
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Côté application : connexion avec `REVERB_APP_KEY` sur `ws://127.0.0.1:8080`,
et autorisation des canaux sur `POST /broadcasting/auth` avec le Bearer token,
comme pour le reste de l'API.

Pour suivre ce qui se passe : `php artisan reverb:start --debug`.

## Production, sur le VPS

Tout ce qui suit est **manuel**, à faire sur le serveur.

### 1. Variables

Dans le `.env` de production, des secrets **différents** de ceux du
développement :

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<valeur aléatoire>
REVERB_APP_KEY=<valeur aléatoire>
REVERB_APP_SECRET=<valeur aléatoire>
REVERB_HOST=meriz.example            # le domaine public, vu du client
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1         # le service n'écoute qu'en local
REVERB_SERVER_PORT=8080
```

`REVERB_HOST` est ce que le client utilise, `REVERB_SERVER_HOST` ce que le
service écoute. Le service ne doit **jamais** écouter sur `0.0.0.0` : il
n'est joignable que par nginx, sur la machine.

### 2. Pare-feu

Seuls 80 et 443 sont ouverts. Le port 8080 reste fermé de l'extérieur.

```bash
sudo ufw allow 80,443/tcp
sudo ufw deny 8080/tcp
```

### 3. Le service, maintenu en vie par systemd

Fichier `/etc/systemd/system/meriz-reverb.service` :

```ini
[Unit]
Description=Meriz Reverb (websocket)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/meriz-api
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=3
# Le service garde le code en mémoire : à redémarrer après chaque déploiement.

[Install]
WantedBy=multi-user.target
```

Puis :

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now meriz-reverb
sudo systemctl status meriz-reverb
```

### 4. Exposition chiffrée, par nginx

Dans le bloc `server` du domaine, à côté de la configuration existante de
l'API, et **sous le même certificat TLS** :

```nginx
location ~ ^/(app|apps)/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;

    # Montée en websocket
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";

    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Une connexion d'observation reste ouverte longtemps.
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
}
```

Puis `sudo nginx -t && sudo systemctl reload nginx`.

Le certificat vient de Let's Encrypt, déjà en place pour le domaine :

```bash
sudo certbot --nginx -d meriz.example
```

Le client se connecte alors en `wss://meriz.example`, sur le port 443, sans
port exotique à ouvrir.

### 5. Après chaque déploiement

```bash
sudo systemctl restart meriz-reverb
```

Sans ce redémarrage, le service continue de tourner avec l'ancien code.

### 6. Vérifications

```bash
sudo systemctl status meriz-reverb        # le service tourne
journalctl -u meriz-reverb -f             # les journaux en direct
ss -lntp | grep 8080                      # écoute bien sur 127.0.0.1 seulement
```

Puis, depuis l'application : ouvrir une observation, faire enregistrer un
élève, et vérifier que l'évènement arrive.

## En cas de souci

- **Abonnement refusé (403)** : le suivi n'est pas activé sur le devoir, ou
  le compte n'est pas le prof de la classe. C'est le comportement attendu.
- **Connexion refusée côté client** : vérifier `REVERB_APP_KEY` côté
  application, et que nginx relaie bien `/app` et `/apps`.
- **Rien n'arrive alors que l'élève enregistre** : vérifier que le devoir a
  le suivi activé, et que le service a été redémarré après le déploiement.
