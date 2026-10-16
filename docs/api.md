# Les points d'entrée de l'API

Les 83 routes du serveur, par domaine. Relevé depuis `routes/api.php` : en cas
de doute, c'est ce fichier qui fait foi, et `php artisan route:list` qui le
confirme.

Les canaux temps réel ne sont pas des routes HTTP, ils sont dans
[websocket.md](websocket.md).

## Ce qui vaut partout

### Authentification

Tout passe par un jeton Bearer, jamais par un cookie :

```http
Authorization: Bearer 42|meriz_xxxxxxxxxxxxxxxx
Accept: application/json
```

Le jeton vient de `/api/auth/register` ou `/api/auth/login`, et il expire au
bout de 30 jours. Quatre routes seulement sont publiques : la sonde,
l'inscription, la connexion, et le lien de vérification d'email — celui-là est
authentifié par sa signature, pas par un jeton.

### La forme des réponses

Tout est enveloppé dans `data` :

```json
{ "data": { "id": "01m4x…", "name": "Mon MCD" } }
```

Les listes paginées ajoutent `links` et `meta` :

```json
{
  "data": [ … ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "per_page": 20, "total": 57, … }
}
```

### La forme des erreurs

```json
{ "message": "Le champ contenu est obligatoire.", "errors": { "content": ["…"] } }
```

`errors` n'apparaît que sur une 422. Tous les messages sont en français.

| Code | Quand |
|---|---|
| 401 | Jeton absent, invalide ou expiré |
| 403 | Le droit manque, avec un message qui dit lequel |
| 404 | La ressource n'existe pas **ou** ne te concerne pas |
| 422 | Les données envoyées ne passent pas la validation |
| 429 | Limite de débit atteinte, avec l'entête `Retry-After` |

**Le 404 est volontairement indiscernable.** Le document d'un autre élève, le
brouillon d'un prof, le groupe dont tu n'es pas membre : tous répondent
exactement comme une ressource inexistante, même corps, même code. Une réponse
qui dirait « ça existe mais ce n'est pas à toi » serait déjà une fuite.

### Les contenus de schémas

Le contenu d'un document, d'une base de devoir ou d'un rendu voyage comme une
**chaîne de caractères contenant du JSON**, pas comme un objet :

```json
{ "name": "Mon MCD", "content": "{\"entities\":[…]}" }
```

Le serveur vérifie seulement que c'est du JSON valide et que la taille est
bornée. Il ne lit pas la structure, et il la stocke telle quelle, octet pour
octet : c'est le format de fichier de l'application, et lui seul le comprend.

### Limites de débit

Un plafond général de 300 requêtes par minute et par compte s'applique à tout
l'espace authentifié. Les routes sensibles ont en plus leur propre limite, bien
plus basse, indiquée dans les tableaux ci-dessous. Les deux s'appliquent, la
plus basse gagne.

---

## Santé

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/health` | public | 60/min |

## Comptes et authentification

| | Route | Accès | Débit |
|---|---|---|---|
| POST | `/api/auth/register` | public | 5/min, 20/h par IP |
| POST | `/api/auth/login` | public | 5/min par email+IP, 20/min par IP |
| POST | `/api/auth/logout` | jeton | révoque le jeton courant |
| GET | `/api/auth/email/verify/{id}/{hash}` | lien signé | 30/min par IP |
| POST | `/api/auth/email/verification-notification` | jeton | 2/min, 10/h |
| POST | `/api/auth/password/forgot` | public | 3/min et 10/h par adresse, 10/min par IP |
| POST | `/api/auth/password/reset` | public | 6/min par IP |

L'inscription et la connexion renvoient le compte et son jeton. La vérification
d'email répond par une **redirection vers l'application**, avec le résultat
dans le paramètre `verification` : `success`, `already`, `expired` ou `invalid`.
Détails dans [email.md](email.md).

**Mot de passe oublié**, en deux temps. `/password/forgot` prend une adresse et
répond **toujours la même chose**, mot pour mot, que l'adresse existe ou non :

```json
{ "message": "Si un compte existe pour cette adresse, un lien de réinitialisation vient de partir." }
```

Le lien reçu mène à l'application, pas à l'API, avec `token` et `email` dans
l'adresse. Elle demande le nouveau mot de passe, puis appelle
`/password/reset` avec les trois champs `token`, `email` et `password`. Le
jeton vaut 60 minutes et ne sert qu'une fois.

Trois choses se passent à la réinitialisation, et les deux dernières comptent :
le mot de passe change, l'adresse devient **vérifiée** — suivre ce lien prouve
qu'on lit cette boîte — et **tous les jetons du compte sont révoqués**, sur
tous les appareils. C'est là qu'un compte volé se reprend.

La réponse ne contient **aucun jeton** : l'application repasse par la
connexion. Un lien reçu par email n'ouvre donc jamais de session à lui seul.

## Son propre compte

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/me` | jeton | |
| PATCH | `/api/me` | jeton | 60/min |
| POST | `/api/me/teacher-role` | jeton | 6/min |
| GET | `/api/me/export` | jeton | 3/h, 10/jour |
| DELETE | `/api/me` | jeton + mot de passe | 5/h |

`GET /api/me` est la **seule** réponse de toute l'API qui contient l'email de
connexion. Partout ailleurs, une personne est rendue en profil public : nom,
prénom, et les champs qu'elle a choisi de partager.

Le mode prof exige une adresse scolaire **et** un email vérifié.

`GET /api/me/export` renvoie un fichier JSON téléchargeable avec toutes ses
données. `DELETE /api/me` exige le mot de passe courant dans le corps et
supprime définitivement : voir [securite.md](securite.md).

## Classes

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/classrooms` | jeton | |
| POST | `/api/classrooms` | prof | 60/min |
| POST | `/api/classrooms/join` | jeton | 10/min, 50/h |
| GET | `/api/classrooms/{classroom}` | prof ou membre | |
| PATCH | `/api/classrooms/{classroom}` | prof de la classe | 60/min |
| DELETE | `/api/classrooms/{classroom}` | prof de la classe | 60/min |
| POST | `/api/classrooms/{classroom}/code` | prof de la classe | 60/min |
| DELETE | `/api/classrooms/{classroom}/members/{member}` | prof de la classe | 60/min |
| DELETE | `/api/classrooms/{classroom}/membership` | membre | 60/min |

On rejoint par code. Le code n'est visible que du prof de la classe.

## Devoirs

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/assignments` | jeton | |
| GET | `/api/classrooms/{classroom}/assignments` | prof ou membre | |
| POST | `/api/classrooms/{classroom}/assignments` | prof de la classe | 60/min |
| GET | `/api/assignments/{assignment}` | prof, ou membre si publié | |
| PATCH | `/api/assignments/{assignment}` | prof du devoir | 60/min |
| DELETE | `/api/assignments/{assignment}` | prof du devoir | 60/min |
| POST / DELETE | `/api/assignments/{assignment}/publication` | prof du devoir | 60/min |
| POST / DELETE | `/api/assignments/{assignment}/solution-release` | prof du devoir | 60/min |
| POST | `/api/assignments/{assignment}/image` | prof du devoir | 20/min |
| GET | `/api/assignments/{assignment}/image` | prof, ou membre si publié | |
| DELETE | `/api/assignments/{assignment}/image` | prof du devoir | 60/min |
| POST | `/api/assignments/{assignment}/start` | membre de la classe | 20/min |
| POST | `/api/assignments/{assignment}/copy` | membre de la classe | 20/min, ancien nom de `/start` |

Un brouillon est invisible des élèves : 404, comme s'il n'existait pas. Le
corrigé ne sort jamais avant d'avoir été libéré par le prof.

`POST /start` crée le travail de l'élève et le rattache au devoir, en copiant
la base si le devoir en a une. **C'est la route à appeler quand un élève ouvre
un devoir**, même sans base : sans elle, le prof ne voit pas qu'il a commencé.
L'ancien nom `/copy` reste accepté pour l'instant.

## Documents

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/documents` | jeton | |
| POST | `/api/documents` | jeton | 60/min |
| GET | `/api/documents/{document}` | propriétaire, ou membre du groupe | |
| PUT / PATCH | `/api/documents/{document}` | propriétaire, ou membre du groupe | 60/min |
| DELETE | `/api/documents/{document}` | auteur, ou créateur du groupe | 60/min |

`GET /api/documents` est **strictement personnel** : les documents partagés se
listent par leur groupe. La liste ne renvoie pas les contenus, seulement les
noms et les dates ; le contenu arrive en vue détaillée.

## Rendus

| | Route | Accès | Débit |
|---|---|---|---|
| PUT | `/api/assignments/{assignment}/submission` | membre de la classe | 60/min |
| GET | `/api/assignments/{assignment}/submission` | son auteur | |
| GET | `/api/assignments/{assignment}/submissions` | prof du devoir | |
| GET | `/api/submissions/{submission}` | son auteur, ou le prof | |
| POST | `/api/submissions/{submission}/grade` | prof du devoir | 60/min |
| DELETE | `/api/submissions/{submission}/grade` | prof du devoir | 60/min |

Un rendu par élève et par devoir : un second envoi remplace le premier. Une
fois noté, il est verrouillé — le prof peut retirer la note pour le réouvrir.
La note est un texte libre, « 16/20 » comme « Acquis ».

## Suivi en direct

| | Route | Accès | Débit |
|---|---|---|---|
| POST / DELETE | `/api/assignments/{assignment}/live-tracking` | prof du devoir | 60/min |
| GET | `/api/assignments/{assignment}/live` | prof, suivi activé | 120/min |
| GET | `/api/assignments/{assignment}/live/{student}` | prof, suivi activé | 120/min |
| POST | `/api/assignments/{assignment}/live/{student}/seen` | prof, suivi activé | 120/min |

Lecture seule, et **transparent** : le drapeau `live_tracking` est visible des
élèves, et chaque lecture laisse une trace que l'élève voit sur son travail. Le
prof ne voit que le travail rattaché à ce devoir, jamais un document personnel.

## Cours et médias

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/lessons` | jeton | |
| GET | `/api/classrooms/{classroom}/lessons` | prof ou membre | |
| POST | `/api/classrooms/{classroom}/lessons` | prof de la classe | 60/min |
| GET | `/api/lessons/{lesson}` | prof, ou membre si publié | |
| PATCH | `/api/lessons/{lesson}` | prof du cours | 60/min |
| DELETE | `/api/lessons/{lesson}` | prof du cours | 60/min |
| POST / DELETE | `/api/lessons/{lesson}/publication` | prof du cours | 60/min |
| POST | `/api/lessons/{lesson}/media` | prof du cours | 20/min |
| GET | `/api/lessons/{lesson}/media/{medium}` | prof, ou membre si publié | |
| DELETE | `/api/lessons/{lesson}/media/{medium}` | prof du cours | 60/min |

Un cours est une suite de blocs ordonnés, envoyés comme JSON opaque. Les médias
acceptés sont les images et les audios, **vérifiés sur leur contenu réel** et
non sur leur nom ou leur extension. Ils vivent sur un disque privé et ne sont
servis qu'à qui peut voir le cours.

## Groupes

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/groups` | jeton | |
| POST | `/api/groups` | jeton | 60/min |
| POST | `/api/groups/join` | jeton | 10/min, 50/h |
| GET | `/api/groups/{group}` | créateur ou membre | |
| PATCH | `/api/groups/{group}` | créateur | 60/min |
| DELETE | `/api/groups/{group}` | créateur | 60/min |
| POST | `/api/groups/{group}/code` | créateur | 60/min |
| DELETE | `/api/groups/{group}/members/{participant}` | créateur | 60/min |
| DELETE | `/api/groups/{group}/membership` | membre | 60/min |
| GET | `/api/groups/{group}/documents` | créateur ou membre | |
| POST | `/api/groups/{group}/documents` | créateur ou membre | 60/min |

Un groupe est un espace de travail entre élèves, sans prof ni note. Ses
documents appartiennent au groupe : quitter le groupe ferme l'accès, même à qui
les a écrits.

## Commentaires

| | Route | Accès | Débit |
|---|---|---|---|
| GET | `/api/documents/{document}/comments` | élève, prof du devoir, ou membre du groupe | |
| POST | `/api/documents/{document}/comments` | les mêmes | 60/min |
| POST / DELETE | `/api/comments/{comment}/resolution` | les mêmes | 60/min |
| DELETE | `/api/comments/{comment}` | auteur, prof du devoir, créateur du groupe | 60/min |

Un commentaire est général, ou **posé à un point du schéma** avec deux
coordonnées stockées telles quelles. Commenter ne dépend pas du suivi en
direct : un prof corrige quand il veut, l'élève lui répond.

## Vues d'ensemble

| | Route | Accès |
|---|---|---|
| GET | `/api/overview/to-grade` | prof |
| GET | `/api/overview/my-assignments` | élève |

Deux routes de confort, pensées pour un écran d'accueil : tout ce qu'un prof a
à corriger sur toutes ses classes, et tous les devoirs d'un élève avec l'état
de son travail. Chacune répond en un appel, avec un nombre de requêtes
constant.
