# CLAUDE.md

Ce fichier est lu par Claude Code au début de chaque session sur le dépôt du serveur Meriz. Il fixe le contexte et les règles. Respecte-le pour tout ce que tu produis.

## Le projet

C'est Meriz API, le serveur de la plateforme Meriz. C'est une API JSON découplée. L'application Meriz, la même base de code en version web et en logiciel Tauri, l'appelle par internet.

Point d'architecture fondateur. Le serveur est une API seulement, il renvoie du JSON, il ne rend jamais de page. L'interface vit dans l'autre dépôt, celui de l'application. On n'utilise ni Blade ni Inertia pour des pages, car le front est une application autonome qui tourne aussi en logiciel de bureau, où il n'y a pas de serveur pour rendre des pages.

## Rôle et frontière

Le serveur gère les comptes, la synchronisation des documents dans le cloud, les classes, puis plus tard les devoirs, les rendus et les commentaires. Il ne rend jamais l'interface. Il stocke et il autorise, rien de plus.

## Stack technique

- Laravel 13, la version actuelle
- PHP 8.3 ou plus, avec Composer
- Laravel Sanctum pour l'authentification
- Base de données, SQLite en développement, un simple fichier, et MySQL ou PostgreSQL en production, plus tard

## Authentification

Par jeton, un Bearer token, et non par cookie. La connexion et l'inscription renvoient un jeton, que le front stocke et envoie dans l'entête Authorization à chaque requête. On n'utilise pas le mode cookie de Sanctum, car le front n'est pas sur le même domaine et inclut une application de bureau. Le jeton fonctionne pareil depuis le site et depuis le logiciel.

## Décisions actées

- Connexion par email. Un statut scolaire ou universitaire est vérifié par le domaine de l'email.
- Deux rôles, élève et prof.
- Code de classe pour rejoindre vite, mais l'élève est connecté à son compte, jamais en anonyme, pour garder le suivi.
- Classes simples, pas de couche établissement ou école pour l'instant.
- Commentaires contextuels attachés au travail, pas de chat.

## Modèle de données, direction générale

- Utilisateurs, avec rôle et statut scolaire vérifié.
- Documents, appartenant à un utilisateur, avec un nom, des dates, et un contenu JSON qui est exactement le format de fichier produit par l'application. Le serveur stocke ce contenu, il ne l'interprète pas.
- Classes, appartenant à un prof, avec un code, et des membres reliés par une table pivot.
- Plus tard, devoirs, rendus, commentaires.

## Sécurité, exigence forte

- Valider toute entrée, via des Form Requests.
- Autoriser tout accès, via des Policies.
- Cloisonner les données par utilisateur. Un élève ne voit que ses documents, un prof ne voit que le travail de ses classes.
- Limiter le débit sur les points sensibles, connexion, inscription, rejoindre par code.
- Ne jamais faire confiance aux données du client.

## Conventions Laravel

- Routes d'API dans routes/api.php.
- Contrôleurs d'API fins, la logique dans des services ou des actions au besoin.
- Form Requests pour la validation, API Resources pour les réponses JSON, Policies pour l'autorisation, migrations pour le schéma.
- Réponses JSON cohérentes, mêmes formes de succès et d'erreur.

## CORS

Autoriser les origines du front, le développement sur localhost port 5173, le domaine web de production, et l'application Tauri. Sans quoi le front ne pourra pas appeler l'API.

## Style de la documentation

Français clair et direct, phrases courtes, voix active, sans tics d'IA, comme dans l'application.

## Hors périmètre pour l'instant

Temps réel et websockets, vue en direct, co-édition, chat, couche établissement. Ce sont des phases ultérieures.
