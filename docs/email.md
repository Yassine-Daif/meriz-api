# Envoi d'emails et vérification d'adresse

Le serveur envoie un seul email pour l'instant : le lien de confirmation
d'adresse, à l'inscription et sur demande. Ce lien sert de garde-fou au mode
prof, qui exige une adresse scolaire **et** confirmée.

## En développement

Rien à installer. `MAIL_MAILER=log` écrit l'email dans
`storage/logs/laravel.log` au lieu de l'envoyer. Le lien se lit directement
dans le journal :

```bash
php artisan serve
# après une inscription
grep -o 'http://localhost/api/auth/email/verify[^"]*' storage/logs/laravel.log | tail -1
```

Dans le journal, le lien est encodé en quoted-printable : les `=` de fin de
ligne sont des coupures, à retirer avant de suivre le lien.

## En production

Tout passe par les variables d'environnement, aucun code à toucher.

```dotenv
MAIL_MAILER=smtp          # ou ses, postmark, resend, sendmail
MAIL_HOST=smtp.exemple.fr
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="bonjour@meriz.example"
MAIL_FROM_NAME="Meriz"
```

Trois réglages comptent autant que les identifiants du service mail :

- **`APP_URL` doit être l'adresse publique de l'API**, en https. Le lien
  envoyé est signé à partir de cette valeur : si elle est fausse, toutes les
  signatures sont refusées.
- **`FRONTEND_URL`** est l'adresse de l'application, celle où la personne est
  renvoyée après la validation.
- **`AUTH_VERIFICATION_EXPIRE`**, en minutes, est la durée de validité du
  lien. 60 par défaut.

L'envoi se fait pendant la requête, il n'y a aucun worker de file à faire
tourner. Si le service mail tombe, l'inscription réussit quand même, l'échec
part dans les logs, et la personne redemande un lien.

## Ce que l'application doit prévoir

**Une page de retour**, à l'adresse `FRONTEND_EMAIL_VERIFIED_PATH`
(`/email-verifie` par défaut). Le serveur y renvoie après avoir validé le
lien, avec le résultat dans le paramètre `verification` :

| Valeur | Ce qui s'est passé | Quoi afficher |
|---|---|---|
| `success` | L'adresse vient d'être confirmée | Confirmation, puis retour à l'application |
| `already` | Le lien avait déjà été utilisé | Même chose : le compte est confirmé |
| `expired` | Le lien a passé sa durée de validité | Proposer d'en redemander un |
| `invalid` | Lien falsifié, ou compte inconnu | Proposer d'en redemander un |

**Un bouton « renvoyer le lien »**, pour un compte connecté :

```http
POST /api/auth/email/verification-notification
Authorization: Bearer <jeton>
```

202 avec un message quand le lien part, 200 quand l'adresse est déjà
confirmée. 429 au-delà de deux demandes par minute ou dix par heure.

**L'état du compte** se lit dans `GET /api/me` : `email_verified_at` vaut
`null` tant que l'adresse n'est pas confirmée. C'est ce qui permet d'afficher
un rappel, et d'expliquer pourquoi le mode prof est refusé :
`POST /api/me/teacher-role` répond alors 403 avec le message
« Confirme ton adresse email avant d'activer le mode prof. »

Un compte non confirmé reste utilisable comme élève : documents, classes,
devoirs, rendus, groupes. Seul le mode prof est verrouillé.
