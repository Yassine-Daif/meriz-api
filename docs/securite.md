# Revue de sécurité du serveur

Revue complète menée avant la mise en service. Ce document garde les constats,
ce qui a été corrigé, et surtout **ce qui n'a pas été corrigé et pourquoi** :
c'est cette dernière partie qui sert quand on reprend le code dans six mois.

Les points de configuration, eux, sont dans [deploiement.md](deploiement.md).

## Ce qui tient

Vérifié point par point, pas coché de loin.

| Domaine | État |
|---|---|
| Autorisation | Toutes les routes d'API sous `auth:sanctum`, sauf quatre volontairement publiques : la sonde, l'inscription, la connexion, le lien signé de vérification. Chaque contrôleur appelle `Gate::authorize`, sauf ceux qui n'agissent que sur le compte du jeton. |
| Cloisonnement | Chaque identifiant de route est résolu dans un `visibleTo($user)`, ou parmi les enfants de la ressource déjà résolue. Un identifiant d'autrui donne le même 404 qu'un identifiant inexistant, même en debug. |
| Affectation de masse | `#[Fillable]` réduit sur chaque modèle. Ni rôle, ni statut scolaire, ni email vérifié, ni propriétaire, ni rattachement, ni chemin de fichier n'est remplissable : tout passe par `forceFill` dans les actions, depuis le jeton. |
| Validation | Une Form Request par point d'entrée, l'autorisation dans `authorize()`, donc avant la validation. Contenus JSON bornés en octets puis validés. |
| Fichiers | Type décidé par `finfo` sur les octets réels, nom de stockage généré, nom d'affichage nettoyé, disque privé hors racine web, servis derrière une Policy. Aucun chemin ne vient du client. |
| Divulgation | Une seule Resource porte l'email de connexion, rendue au seul titulaire. Corrigés, notes et brouillons derrière leurs Policies. Aucun chemin de stockage exposé. |
| Injection | Tout en Eloquent paramétré. Les quatre seuls fragments SQL bruts sont des chaînes littérales, sans interpolation. |
| Mots de passe et jetons | Hachés, cachés à la sérialisation, jamais renvoyés. Jetons préfixés, expirant, purgés chaque jour. |
| Liens signés | Signature vérifiée, expiration distinguée de la falsification, usage unique, redirection vers une adresse qui vient uniquement de la configuration. |
| Websocket | Les deux canaux délèguent aux Policies déjà testées. Les évènements de client ne sont acceptés que des membres du canal. |
| Secrets | `.env` hors du dépôt, `.env.example` sans aucune valeur réelle. |
| Dépendances | `composer audit` sans alerte. |

## Ce qui a été corrigé

1. **Limites de débit, couverture complète.** Les lectures authentifiées n'avaient aucune limite : un jeton volé pouvait aspirer tout ce qu'il voyait à pleine vitesse. Un plafond général `throttle:api` (300 par minute et par compte) couvre maintenant tout le groupe authentifié, la sonde est à 60 par minute, et l'autorisation des canaux à 120. Les routes sensibles gardent leur limite propre, bien plus basse : les deux s'appliquent, la plus basse gagne.
2. **Entêtes de sécurité sur toutes les réponses.** `App\Http\Middleware\SecurityHeaders` pose `nosniff`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY` et une CSP `default-src 'none'` sur chaque réponse, erreurs comprises, sans écraser celles que les routes de fichiers posent déjà.
3. **Expiration des jetons non désactivable par erreur.** `SANCTUM_TOKEN_EXPIRATION` vide, nul ou négatif retombe sur 30 jours au lieu de valoir « jamais ». Il n'y a plus de réglage qui rende les jetons éternels en silence.

Chacun est gardé par des tests dans `tests/Feature/Security/`. Deux d'entre eux
parcourent la table de routage : ils tombent dès qu'une route future oublie le
jeton ou sa limite de débit.

## Ce qui n'est pas corrigé, et pourquoi

### L'inscription révèle qu'une adresse a déjà un compte

`POST /api/auth/register` avec une adresse déjà prise répond 422 « cette adresse
est déjà utilisée ». On peut donc vérifier si quelqu'un est inscrit, ce qui
compte sur une plateforme où les comptes sont souvent ceux de mineurs.

**Gardé volontairement.** La seule correction étanche serait de ne plus jamais
rendre de jeton à l'inscription et de tout passer par un email, ce qui refait le
parcours d'inscription des deux côtés. Garde-fous en place : 5 inscriptions par
minute et 20 par heure et par IP, et une adresse scolaire ne suffit plus à
devenir prof sans vérification d'email.

Si on veut fermer ce point plus tard : répondre 202 sans jeton quand l'adresse
existe, et envoyer un email au titulaire.

### La limite de débit de Reverb est désactivée

Une session de co-édition Yjs envoie beaucoup de petits messages ; la limite par
défaut de 60 par minute la casserait. Un membre d'un canal peut donc y envoyer
beaucoup d'évènements. Ce qui borne encore : il faut être membre du canal, donc
avoir passé la Policy, `max_message_size` est à 3 Mo et `max_connections` est
réglable.

### Un prof peut « commencer » son propre devoir

`AssignmentPolicy::copyBase` délègue à `view`, donc le prof de la classe peut
appeler `POST /api/assignments/{id}/start`, ce qui lui crée un document de
travail inutile dans ses documents. Aucune fuite, aucun accès gagné : laissé tel
quel pour ne pas changer le contrat de l'application.

### Pas de réinitialisation de mot de passe

Elle n'existe pas encore. Qui perd son mot de passe perd son compte. C'est une
phase à part, pas un défaut de cette revue.

## Hors périmètre de cette revue

Double authentification, journal d'audit des accès, chiffrement au repos des
contenus, pare-feu applicatif.
