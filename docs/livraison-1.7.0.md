# Livraison 1.7.0, 5 novembre 2026

Le lot ajoute les modes Débutant/Complet, les catégories d’inscription, les
réglages d’interface des devoirs avec aperçu, les regroupements annuels de
classes et de groupes, la bibliothèque pédagogique, la diffusion vers plusieurs
classes et la préparation d’une nouvelle année. Il améliore l’affichage des noms
longs et limite les rafales de création et le volume des documents.

Les raccordements RENATER/eduGAIN/URCA/ENT/GAR et le rattachement institutionnel
facultatif des élèves sont explicitement reportés. Le fonctionnement actuel des
invitations de référents et ses prérequis sont décrits dans
[enseignement.md](enseignement.md). Aucun établissement ni référent réel n’a
été ajouté pendant cette livraison.

## Vérifications

- API : 569 tests, 3 885 assertions, réussis avant et après les correctifs de
  dépendances ; contrôle Pint réussi. La CI vérifie désormais PHP 8.4 et 8.5,
  conformément au minimum réel PHP 8.4.1 des dépendances verrouillées.
- Application : 1 032 tests sur 80 fichiers, 5 tests de publication, lint,
  TypeScript et compilation de production réussis.
- Navigateur en production : choix initial et mémorisation, deux niveaux sur un
  même groupe, coédition, observation silencieuse, correction visible, présence,
  dernières lectures, restrictions actualisées pendant la séance, aperçu sans
  sauvegarde et conservation du brouillon de réglage au retour.
- Diffusion en production : seconde classe sans doublon dans la source,
  nouvelle année sans anciens membres ni résultats, ressources en brouillon,
  copie indépendante vers un groupe.
- Français/anglais, clair/sombre, largeurs 390, 768 et 1 440 pixels, noms longs,
  focus du dialogue, fermeture au clavier, annulation/rétablissement transmis et
  réouverture d’un document avec reprise du direct.
- Installateurs Windows, Linux et macOS Intel/Apple Silicon compilés par la CI.
  Les quatre entrées du manifeste de mise à jour comportent une signature et un
  lien accessible ; les liens de la page de téléchargement suivent la 1.7.0.
  Ces contrôles ne constituent pas un essai manuel d’installation sur chaque OS.

L’avertissement Vite de taille du paquet principal reste présent : environ
823 ko avant compression, 249 ko gzip. La compilation réussit.

## Déploiement et sécurité

L’API a été déployée avant le client. Les huit migrations du 5 novembre ont
réussi sur MariaDB. Configuration, routes, événements et Filament ont été remis
en cache, PHP-FPM rechargé et Reverb redémarré. Le fichier `.env` n’a pas changé.
Le web et la vitrine ont été publiés par le réceptionnaire de déploiement
habituel, avec échange atomique des dossiers, conservation des droits et des
anciens assets utiles aux fenêtres déjà ouvertes.

L’audit a identifié 11 alertes préexistantes, corrigées avec six mises à jour
limitées aux versions correctives : Livewire 4.4.8, Symfony HTML Sanitizer,
HTTP Foundation, HTTP Kernel et Routing 8.1.11, Mime 8.1.10. L’audit de production
ne signale plus d’alerte après installation.

Une sauvegarde de la base et des médias a été contrôlée avant les migrations.
Le code remplacé et les dépendances précédentes sont conservés dans les
sauvegardes privées de livraison sous `/var/backups/meriz/releases/`. Un retour
au code précédent doit conserver les nouvelles données ; ne pas supprimer les
tables ou colonnes utilisées sans sauvegarde et procédure de restauration.

Les scénarios de production utilisent uniquement trois comptes synthétiques
créés pour le diagnostic, sans envoi d’email. Les comptes, jetons et données de
diagnostic sont supprimés après les essais.

Le contrôle final ne relève aucune nouvelle erreur Laravel depuis le
déploiement et aucune erreur Reverb. Une ancienne erreur de connexion MariaDB
du 5 novembre à 06:39 UTC figure dans le journal ; elle précède cette livraison
et ne s’est pas reproduite pendant les essais. PHP-FPM et Reverb sont actifs,
la route de profil refuse correctement les appels anonymes et la page de
connexion admin répond.

Code applicatif livré : `meriz` au commit `9254dc8`, tag `v1.7.0`.
API fonctionnelle : `b07a0bb`, prérequis PHP corrigé dans `042c77d`,
dépendances corrigées dans `6f25443`. Les contrôles CI PHP 8.4 et 8.5 et le
workflow de publication des quatre plateformes réussissent.
