# Meriz API

[![Tests](https://github.com/Yassine-Daif/meriz-api/actions/workflows/ci.yml/badge.svg)](https://github.com/Yassine-Daif/meriz-api/actions/workflows/ci.yml)
[![Licence AGPL 3.0](https://img.shields.io/badge/licence-AGPL--3.0-blue)](LICENSE)

Le serveur de la plateforme Meriz, un outil de modélisation de bases de données
pour l'enseignement : des élèves dessinent des schémas, des profs donnent des
devoirs, suivent le travail et le corrigent.

Ce dépôt ne contient **que le serveur**. C'est une API JSON, authentifiée par
jeton. L'interface vit dans un autre dépôt, en version web et en logiciel de
bureau Tauri, et appelle cette API par internet. Le serveur stocke et autorise,
il ne rend pas de page — à une exception près, la zone d'administration.

## La stack

| | |
|---|---|
| Framework | Laravel 13, PHP 8.3 ou plus |
| Authentification | Laravel Sanctum, jetons Bearer, jamais de cookie de session pour l'API |
| Temps réel | Laravel Reverb, pour l'observation et la co-édition |
| Administration | Filament, sur `/admin`, réservée à un compte administrateur |
| Base de données | SQLite en développement, MySQL ou PostgreSQL en production |

## Démarrer en développement

**Prérequis** : PHP 8.3 ou plus avec les extensions `intl` et `zip` activées, et
Composer. L'extension `intl` est exigée par Filament, `zip` par Composer :
elles ne sont pas toujours actives sur une installation neuve, vérifie avec
`php -m`.

```bash
git clone https://github.com/Yassine-Daif/meriz-api.git
cd meriz-api
composer setup   # installe, crée le .env, génère la clé, migre
composer dev     # sert sur http://localhost:8000
```

`composer setup` crée la base SQLite dans `database/database.sqlite`. Elle reste
sur ta machine, elle n'est pas suivie par git.

Pour vérifier que tout répond :

```bash
curl http://localhost:8000/api/health
# {"status":"ok"}
```

### Deux commandes qui font gagner du temps

```bash
php artisan meriz:demo-data          # un prof, trois classes, 42 élèves de test
php artisan meriz:admin ton@email.fr # accès à la zone d'administration
```

`meriz:demo-data` affiche les identifiants qu'elle crée. Elle refuse de
s'exécuter en production sans `--force`.

### Temps réel

L'observation en direct et la co-édition passent par un websocket, servi par un
second processus :

```bash
php artisan reverb:start
```

Tout le reste de l'API fonctionne sans lui. Détails dans
[docs/websocket.md](docs/websocket.md).

## Les tests

500 tests couvrent l'API, dont une bonne part vérifie le cloisonnement des
données : qu'un élève ne voit pas le travail d'un autre, qu'un prof ne voit pas
une classe qui n'est pas la sienne.

```bash
composer test            # ou php artisan test
vendor/bin/pint          # style de code, --test pour seulement vérifier
```

La suite tourne sur une base SQLite en mémoire, il n'y a rien à préparer.

## La structure

| Dossier | Ce qu'on y trouve |
|---|---|
| `routes/api.php` | Les 83 points d'entrée de l'API |
| `app/Http/Controllers` | Contrôleurs fins : autoriser, déléguer, répondre |
| `app/Http/Requests` | La validation des entrées, une Form Request par point d'entrée |
| `app/Http/Resources` | Ce qui sort en JSON, en liste blanche de champs |
| `app/Policies` | Les autorisations, le cœur du cloisonnement |
| `app/Actions` | La logique métier, une action par geste |
| `app/Models` | Les modèles Eloquent et leurs portées de visibilité |
| `app/Filament` | La zone d'administration |
| `database/migrations` | Le schéma |
| `tests/Feature` | Les tests, groupés par domaine |
| `docs/` | Le reste de la documentation |

## Documentation

| Document | Sujet |
|---|---|
| [docs/api.md](docs/api.md) | Toutes les routes, qui y a droit, la forme des réponses et des erreurs |
| [docs/securite.md](docs/securite.md) | Ce qui est protégé, comment, et les limites assumées |
| [docs/admin.md](docs/admin.md) | La zone d'administration et comment désigner un administrateur |
| [docs/email.md](docs/email.md) | Envoi des emails et vérification d'adresse |
| [docs/websocket.md](docs/websocket.md) | Les canaux temps réel, en développement et en production |
| [docs/deploiement.md](docs/deploiement.md) | La liste à dérouler à la mise en ligne |

## Contribuer

Les contributions sont bienvenues : [CONTRIBUTING.md](CONTRIBUTING.md) explique
comment installer, lancer les tests et ouvrir une pull request.

Pour une **faille de sécurité**, n'ouvre pas d'issue publique : la procédure est
dans [SECURITY.md](SECURITY.md).

## Licence

Meriz API est un logiciel libre, publié sous licence [GNU AGPL 3.0](LICENSE).
Toute version modifiée et distribuée, y compris simplement accessible par le
réseau, doit rester ouverte sous la même licence et créditer l'auteur.

Les dépendances gardent leur propre licence : Laravel, par exemple, reste sous
licence MIT.
