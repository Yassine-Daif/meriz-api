# Contribuer à Meriz API

Bienvenue. Ce guide suppose que tu n'as jamais contribué à un projet : si
quelque chose n'est pas clair, c'est le guide qu'il faut corriger, dis-le.

Avant une grosse contribution, ouvre une issue pour en parler. Ça évite
d'écrire trois jours de code qui part dans une direction qu'on ne prendra pas.
Pour un correctif ou une amélioration bornée, va directement à la pull request.

**Une faille de sécurité ne passe pas par une issue ni par une pull
request** : voir [SECURITY.md](SECURITY.md).

## Installer

Il te faut PHP 8.3 ou plus avec les extensions `intl` et `zip`, et Composer.
Vérifie les extensions avec `php -m` : elles ne sont pas toujours actives sur
une installation neuve, et l'installation échoue sans elles.

```bash
git clone https://github.com/Yassine-Daif/meriz-api.git
cd meriz-api
composer setup
composer dev
```

Puis `php artisan meriz:demo-data` pour avoir des données avec lesquelles
travailler : un prof, trois classes, 42 élèves. La commande affiche les
identifiants qu'elle crée.

Pas besoin d'installer de base de données : c'est du SQLite, un simple fichier,
et il reste sur ta machine.

## Lancer les tests

```bash
composer test        # les 500 tests
vendor/bin/pint      # le style de code, corrige en place
```

Les deux doivent être au vert avant d'ouvrir une pull request. L'intégration
continue les relance de toute façon, sur PHP 8.3 et 8.5.

Pour ne lancer qu'une partie, pendant que tu travailles :

```bash
php artisan test tests/Feature/Documents
```

### L'habitude du dépôt : un test par règle

Chaque règle métier a son test, et chaque domaine a ses **tests d'isolation**,
qui prouvent qu'un utilisateur ne voit pas les données d'un autre. Regarde
`tests/Feature/Documents/DocumentIsolationTest.php` pour voir à quoi ça
ressemble.

Si tu ajoutes une route ou une règle d'accès, ajoute le test qui montre qu'un
tiers est refusé. C'est la contribution la plus utile qu'on puisse faire ici.

## Les conventions du projet

Elles viennent de `CLAUDE.md`, à la racine, qui sert de contexte à tout le
monde — humains compris.

**L'architecture, en une phrase** : le serveur est une API JSON, il ne rend
jamais de page. Pas de Blade, pas d'Inertia, parce que l'interface est une
application autonome qui tourne aussi en logiciel de bureau. Seule exception,
assumée et à part : la zone d'administration sur `/admin`.

**Où va quoi** :

| Tu veux… | Ça va dans |
|---|---|
| valider une entrée | une Form Request, `app/Http/Requests` |
| autoriser un accès | une Policy, `app/Policies` |
| décider ce qui sort en JSON | une API Resource, `app/Http/Resources` |
| écrire de la logique métier | une action, `app/Actions` |
| changer le schéma | une migration, `database/migrations` |

Les contrôleurs restent fins : autoriser, déléguer à une action, répondre avec
une Resource. S'ils grossissent, c'est que la logique est au mauvais endroit.

**La règle à ne jamais perdre de vue** : toute donnée est cloisonnée par
utilisateur. Un élève ne voit que ses documents, un prof que le travail de ses
classes. Et un refus prend la forme d'un **404 indiscernable** : la ressource
d'autrui répond exactement comme une ressource inexistante, sans rien dire de
plus. Une route qui répond 403 « ce document appartient à quelqu'un d'autre »
confirme son existence, et c'est déjà une fuite.

Concrètement, trois gardes se superposent, et on garde les trois :

1. la liaison de route ne résout un identifiant que parmi ce que la personne
   peut atteindre ;
2. la Policy revérifie ;
3. la Resource ne laisse sortir qu'une liste blanche de champs.

**Les champs sensibles** — rôle, statut scolaire, propriétaire, rattachement,
email vérifié, droit d'administration — ne sont jamais remplissables depuis une
requête. Ils s'écrivent par `forceFill` dans une action, depuis le jeton.

**En français**, les commentaires, la documentation et les messages renvoyés à
l'utilisateur. Phrases courtes, voix active. On explique le **pourquoi** d'un
choix, pas ce que le code fait déjà lire.

## Ouvrir une pull request

```bash
git switch -c ce-que-tu-fais
# tes modifications, avec les tests qui vont avec
composer test && vendor/bin/pint
git commit -m "feat(documents): ..."
```

Les messages de commit suivent les [Conventional
Commits](https://www.conventionalcommits.org/fr/) : `feat`, `fix`, `docs`,
`test`, `chore`, puis le domaine entre parenthèses. Regarde `git log` pour voir
le ton.

Dans la pull request, dis **ce que ça change et pourquoi**. Si ça touche aux
accès ou au cloisonnement, dis-le franchement : c'est ce qu'on relira en
premier.

Une pull request qui ne passe pas l'intégration continue n'est pas un problème,
c'est une étape. Pousse un correctif, ou demande de l'aide dans la discussion.

## La licence

Meriz API est sous [GNU AGPL 3.0](LICENSE). En proposant une contribution, tu
acceptes qu'elle soit publiée sous cette licence, comme le reste du projet.
Concrètement : ton travail reste libre, et quiconque le modifie et le diffuse
devra laisser le sien libre aussi.
