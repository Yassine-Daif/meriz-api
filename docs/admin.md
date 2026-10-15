# La zone d'administration

Un backoffice Filament sur `/admin`, réservé à un compte administrateur : des
chiffres sur la plateforme, et de quoi retrouver et corriger les données sans
passer par `php artisan tinker`.

## Une exception assumée à la règle « API seulement »

CLAUDE.md pose que le serveur ne rend jamais de page. Cette règle reste vraie
pour l'API. Le panneau admin est une surface **à part** :

| | API | Zone admin |
|---|---|---|
| Adresse | `/api/*` | `/admin/*` |
| Réponses | JSON seulement | pages rendues |
| Authentification | jeton Bearer | session, cookie |
| Qui | tout compte | `is_admin` seulement |

Les deux mondes sont **étanches**, et c'est testé dans les deux sens :
`config/sanctum.php` déclare `'guard' => []`, donc une session d'administrateur
ne donne **aucun** accès à l'API, et un jeton d'API ne donne **aucun** accès au
panneau. Le panneau n'ajoute aucune route sous `/api` et aucune origine dans
`config/cors.php`.

## Fabriquer un administrateur

En ligne de commande, sur le serveur. C'est la seule porte : il n'y a rien dans
l'application, et **rien dans le panneau** non plus — un administrateur ne doit
pas pouvoir en désigner un autre d'un clic.

```bash
php artisan meriz:admin claire@ac-lyon.fr            # donner le droit
php artisan meriz:admin claire@ac-lyon.fr --revoke   # le retirer
```

Le compte doit déjà exister, et ne pas avoir été supprimé par son titulaire.
`is_admin` est absent de `#[Fillable]` et de toutes les API Resources : il ne
s'écrit ni ne se lit par l'API, dans aucune requête.

## Le tableau de bord

Les données du logiciel : comptes, élèves et profs, classes, groupes, devoirs,
rendus en attente de correction, cours, documents, commentaires, et deux
courbes — les nouvelles inscriptions par mois, les rendus par semaine.

Les visites, les sources de trafic et le taux de clics ne sont **pas** ici :
ça relève d'un outil d'analytics branché au déploiement, pas des données
métier.

## Ce qui est modifiable, et ce qui ne l'est pas

La règle : on corrige ce qui identifie et ce qui organise, on ne touche pas aux
contenus produits par les utilisateurs.

| Ressource | Modifiable | Visible, non modifiable | Jamais affiché |
|---|---|---|---|
| Comptes | nom, prénom, statut scolaire, présentation, contact, partages | email, rôle, `is_admin`, email vérifié, anonymisation | mot de passe |
| Classes | nom | code d'accès, prof, nombre de membres | — |
| Devoirs | titre, consigne, type, échéance | classe, état, corrigé livré, suivi en direct | base, corrigé, image |
| Cours | titre | classe, état, nombre de médias | blocs, médias |
| Groupes | nom | code, créateur, membres | — |
| Rendus | — | élève, devoir, état, note, retour, **taille** du travail | le travail rendu |
| Commentaires | texte | auteur, travail, position, résolu | — |
| Documents | nom | propriétaire, nature, **taille**, commentaires | le schéma |

**Rien ne se crée depuis le panneau.** Les données naissent dans
l'application, par l'API, avec ses règles. Le panneau observe, corrige et
supprime.

**Les contenus de travail ne s'affichent jamais**, même en lecture seule :
un schéma, une copie rendue, une page de cours sont le travail de personnes
réelles, souvent mineures. Le panneau en montre la taille, pas le contenu.
Un test parcourt les huit écrans et vérifie qu'aucun contenu n'y apparaît.

### Les états passent par les actions du serveur

Publier un devoir, livrer un corrigé, ouvrir le suivi en direct, noter un
rendu, classer un commentaire : ce sont des **actions** du panneau, et elles
appellent les mêmes classes que l'API (`ChangeAssignmentState`,
`ChangeLessonState`, `GradeSubmission`, `ResolveComment`). Les dates qui vont
avec sont donc posées de la même façon, et il n'y a pas deux règles pour une
même chose.

### Le panneau ne consulte pas les Policies de l'API

Elles répondent à une autre question — « ce prof peut-il voir cette
classe ? » — et plusieurs attendent la ressource parente en paramètre. La
question du panneau est déjà tranchée à l'entrée : seul un administrateur y
accède, et tout lui est ouvert. C'est écrit, et c'est la seule raison, dans
`App\Filament\Resources\AdminResource`.

## Supprimer un compte depuis le panneau

Le bouton n'appelle **pas** la suppression de Filament : il appelle
`App\Actions\Account\DeleteAccount`, la même action que `DELETE /api/me`. Donc
le même résultat, une seule règle dans tout le serveur :

- l'identité est effacée, les documents personnels et les médias partent ;
- un rendu déjà noté reste chez son prof, avec sa note, sous « Compte
  supprimé » ;
- les commentaires restent dans leurs fils, anonymisés.

Une suppression Eloquent ordinaire emporterait les rendus et les commentaires
en cascade, et ferait disparaître la copie notée d'un prof. C'est précisément
ce qu'on évite.

Les autres suppressions passent par les modèles, donc par les crochets déjà en
place : supprimer une classe efface ses dossiers de médias, supprimer un
groupe emporte ses documents partagés.

## En développement

```bash
php artisan serve
php artisan meriz:admin mon.compte@exemple.fr
# puis http://localhost:8000/admin
```

L'extension PHP `intl` est nécessaire (Filament l'exige) : vérifier avec
`php -m | grep intl`.

## Au déploiement

Voir [deploiement.md](deploiement.md) : les assets de Filament à publier après
chaque mise à jour, le premier administrateur à créer en ligne de commande, et
`/admin` derrière HTTPS.
