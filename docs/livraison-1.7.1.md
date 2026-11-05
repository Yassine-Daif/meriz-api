# Livraison 1.7.1, 5 novembre 2026

Les classes et groupes ouvrent leur liste, avec des onglets séparés pour créer,
rejoindre et organiser les espaces. La bibliothèque pédagogique est accessible
dans son propre onglet pour les professeurs. Les regroupements disposent d'un
gestionnaire dédié, avec recherche, effectifs et affichage des regroupements
vides.

Les espaces se déplacent par glisser-déposer ou par une fenêtre accessible au
clavier et sur mobile. La dernière opération peut être annulée. Les notifications
restent flottantes. L'archivage est une action distincte avec confirmation et
restauration ; les regroupements sont créés actifs. Les espaces classés dans
les archives sont signalés dans la liste.

Les périodes affichent directement l'année scolaire, par exemple 2026-2027,
ou l'année civile 2026. Une autre année peut être saisie et la période est
facultative ; les groupes n'en imposent pas. Les anciens regroupements conservent
leur période scolaire. Voir [enseignement.md](enseignement.md) pour les routes
et la compatibilité des clients.

## Vérifications

- API : 577 tests, 3 938 assertions ; Pint réussi. Les nouveaux cas couvrent
  l'isolation des comptes, les déplacements, l'annulation, les tentatives
  répétées, les sources périmées, le destinataire plein et les paramètres
  modifiés après un déplacement dans une autre fenêtre.
- Application : 1 036 tests, contrôle TypeScript, lint et 5 tests du système
  de publication ; compilation avec les variables de production.
- Essais locaux et publics : création active, périodes scolaires et civiles,
  regroupements vides, déplacement par bouton et glisser-déposer, annulation,
  archivage, accès aux archives et restauration.
- Français et anglais, thèmes clair et sombre, largeurs 390, 768 et 1 440 pixels,
  onglets des classes et groupes, formulaires et fenêtres.
- En production, deux fenêtres continuent à coéditer le document pendant le
  déplacement du classement du groupe. Membres et identifiant du groupe restent
  inchangés. L'observation silencieuse et la correction visible sont également
  vérifiées après la refonte.

## Déploiement

API fonctionnelle : `8014f2d`. Application : `7fa9c84`, version 1.7.1.
La sauvegarde de la base et des médias a été contrôlée avant déploiement ; le
code remplacé est conservé dans les sauvegardes privées de livraison.

La migration ajoutant `period_kind`, compatible avec l'ancienne application,
a réussi sur MariaDB avant le remplacement du code. Les caches API et Filament
ont été reconstruits et PHP-FPM rechargé. Le `.env` n'a pas changé et Reverb
n'a pas été redémarré. Les dossiers statiques sont basculés avec le réceptionnaire
habituel, avec conservation des droits et des anciens assets.

L'audit des dépendances de production ne relève pas d'alerte. Aucun nouvel
incident Laravel ou Reverb n'est relevé pendant la livraison. Les trois comptes
de diagnostic, leurs jetons et toutes leurs données sont supprimés après les
essais, sans envoi d'email ni modification des données des utilisateurs réels.

Les raccordements institutionnels restent reportés comme dans la 1.7.0. Cette
livraison ne change aucun rôle, accès à une classe ou à un groupe, ni aucun
contenu de document lors d'un déplacement de classement.
