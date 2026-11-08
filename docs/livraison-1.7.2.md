# Livraison 1.7.2, 8 novembre 2026

Le parcours professeur est simplifié à la demande de l'éditeur. Une personne
choisit Professeur, utilise une adresse scolaire ou universitaire reconnue et
reçoit un email d'activation. Le clic sur le lien confirme l'adresse et active
automatiquement le rôle professeur. L'interface ne demande plus d'invitation ni
de validation manuelle. Les adresses personnelles restent acceptées pour les
autres catégories.

Le domaine est évalué côté serveur. Les académies françaises et les domaines
universitaires et internationaux configurés restent reconnus. Ce choix valide
la possession d'une adresse d'établissement ; il ne constitue pas une
attestation de statut professionnel. Les anciennes attestations sont conservées
pour compatibilité, sans créer de fausse attestation lors d'une activation email.

Un élève n'est pas promu lors de la confirmation de son adresse universitaire.
La promotion automatique demande le choix Professeur. Les comptes déjà déclarés
Professeur avec une adresse scolaire confirmée s'activent à la connexion ou à
l'actualisation de leur profil. Les rôles existants et les permissions sur les
classes et documents restent inchangés.

## Vérifications

- API : 584 tests, 3 977 assertions ; Pint et CI PHP 8.4/8.5 réussis.
- Application : 1 036 tests en CI, lint, TypeScript, compilation et cinq tests
  de publication réussis. Deux délais locaux dépassés pendant une exécution
  chargée ont été revérifiés séparément avec succès, sans changer les tests.
- Navigateur local : inscription professeur, refus d'une adresse personnelle,
  écran sans invitation, message d'activation français et anglais, confirmation
  par le lien et apparition des outils professeur sans reconnexion. Affichage
  contrôlé aux largeurs 390, 768 et 1 440 pixels.
- Production : refus de l'inscription professeur avec une adresse personnelle,
  activation d'un compte de diagnostic par son lien signé, mise à jour de
  l'écran et création d'une classe. Un élève conserve son rôle après confirmation
  et un ancien compte professeur en attente s'active à la connexion.
- Connexion et authentification SMTP réussies. Aucun email d'essai n'a été
  envoyé à une adresse universitaire ; la réception en boîte réelle n'est pas
  une assertion de ces essais. Le lien utilisé dans les scénarios de diagnostic
  appartient uniquement à un compte synthétique créé pour ces essais.
- Logiciels Windows, Linux, macOS Intel et Apple Silicon compilés et publiés.
  Les quatre entrées du manifeste comportent une signature et un lien accessible.
  La page de téléchargement pointe vers la 1.7.2.

## Déploiement

API : `876c0da`. Application : `9c13937`, tag `v1.7.2`.
Base, médias et code ont été sauvegardés avant publication. Aucune migration
ni modification du `.env` n'est nécessaire. Les caches API et Filament sont
reconstruits et PHP-FPM rechargé. Reverb reste actif, sans redémarrage.

L'application et la vitrine sont publiées par le réceptionnaire habituel,
avec bascule atomique et conservation des droits. Les trois comptes de
diagnostic, leurs jetons et leur classe temporaire ont été supprimés.
