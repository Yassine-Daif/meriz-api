# Interfaces et organisation pédagogique

## Préférences et inscription

`users.interface_mode` contient `beginner` ou `complete`. Une valeur nulle signifie
que le premier choix reste à faire. `usage_category` contient l'usage déclaré :
`student`, `teacher`, `professional` ou `individual`. Ces valeurs ne sont pas des
permissions. Elles sont modifiables par `PATCH /api/me`. Les anciennes versions
peuvent continuer à s'inscrire sans catégorie.

Le choix d'interface ne transforme aucun document. Tous les participants continuent
à partager le même modèle Yjs. L'application propose Dictionnaire, MCD et MLD en
mode débutant. Le mode complet conserve MPD, SQL et génération de code.

## Paramétrage des exercices

Un devoir peut avoir `interface_settings = null`, pour respecter le choix personnel,
ou un objet complet :

```json
{
  "views": ["dictionnaire", "mcd"],
  "export_image": true,
  "export_model": false
}
```

Les vues possibles sont `dictionnaire`, `mcd`, `mld`, `mpd`, `sql` et `code`. Il faut
au moins une vue. Seul le professeur propriétaire modifie ces réglages. L'application
élève les relit toutes les dix secondes par
`GET /api/assignments/{assignment}/interface-settings`, sans télécharger l'énoncé,
la base ni le corrigé. Le réglage explicite du devoir prime sur le niveau personnel.

La prévisualisation utilise l'éditeur habituel en lecture seule, sans connexion au
document d'un élève ni changement de rôle. Le professeur peut choisir le niveau
personnel simulé. La restriction de génération porte sur l'interface : les calculs
de MLD et SQL sont locaux. Elle ne constitue pas un dispositif anti-triche et ne
contrôle pas les logiciels extérieurs. Les exports SQL et code sont disponibles
lorsque leurs vues sont autorisées.

## Regroupements privés

`/api/collections` permet de créer, modifier, lister et supprimer des regroupements
personnels de classes ou de groupes. Le type est immuable. L'année de début est
facultative et explicite. L'archivage porte sur le classement personnel, sans
retirer l'accès aux contenus aux autres membres.

Un enseignant ne peut classer que ses propres classes. Un utilisateur ne classe
que les groupes auxquels il a accès. Un regroupement ne crée aucun droit d'accès.
Supprimer un regroupement ne supprime pas ses classes ou groupes.

## Bibliothèque et diffusion multiple

`POST /api/teaching-resources` capture un devoir ou un cours appartenant à une classe
de l'enseignant, avec `source_kind` (`assignment` ou `lesson`) et `source_id`. La
copie conserve les médias sur un emplacement privé indépendant. La classe source
est comptabilisée comme destinataire existant pour éviter une duplication.

`POST /api/teaching-resources/{id}/refresh` crée une nouvelle version depuis un
original choisi du même type. Cette action ne modifie aucune classe destinataire.

`POST /api/teaching-resources/{id}/distribution` reçoit :

- `classroom_ids`, une liste explicite de classes enseignées, sans doublon ;
- `update_existing`, pour propager explicitement une nouvelle version ;
- `publish`, pour publier immédiatement les nouvelles copies seulement ;
- `apply_settings`, pour remplacer aussi les restrictions des devoirs existants ;
- `due_at`, l'échéance des nouvelles copies, ou null.

Une seule publication existe par ressource et classe. Répéter une diffusion ne
crée pas une deuxième copie. Les classes gardent leurs rendus, notes et dates.
Un corrigé remplacé est retenu jusqu'à une nouvelle libération explicite. Les
images et médias des copies sont indépendants. Les références `media_id` des blocs
de cours sont réécrites vers leurs nouvelles copies, les autres contenus restent
inchangés.

La bibliothèque est limitée à 200 ressources et 200 Mio par utilisateur. La
suppression d'une ressource n'efface pas ses copies dans les classes. Les médias
privés de la bibliothèque sont accessibles seulement à son propriétaire par
`/api/teaching-resources/{id}/media/{asset}` et sont référencés dans son export de
données.

`POST /api/collections/{id}/academic-year` prépare une année avec un nom, une
`year_start`, une liste de `resource_ids` et une `request_key` UUID. Les classes
reçoivent de nouveaux codes. Les ressources sélectionnées sont copiées en
brouillon. Élèves, notes, rendus et sessions ne sont pas repris. La clé de demande
évite les doublons en cas de nouvelle tentative.

## Modèles pour les groupes

`POST /api/documents/{id}/group-distribution` reçoit `group_ids` et une
`request_key` UUID. Le document source doit être lisible et les groupes
destinataires accessibles en écriture. Chaque groupe reçoit un document neuf,
sans lien entre ses sessions Yjs et celles des autres groupes. La même clé ne
recrée pas une copie déjà traitée. Une diffusion vise au maximum 20 groupes.

## Création, imports et stockage

Le contenu reste du JSON avec la limite existante de 2 Mio par document. Le serveur
compte les octets UTF-8 dans `documents.content_bytes`. Les budgets de stockage
personnel et de groupe sont séparés, de 100 Mio chacun par défaut :

```ini
DOCUMENTS_MAX_USER_STORAGE_BYTES=104857600
DOCUMENTS_MAX_GROUP_STORAGE_BYTES=104857600
```

Création et agrandissement sont contrôlés sous le verrou du compte ou du groupe.
Un compte dépassant un plafond peut toujours réduire un document ou le renommer.
La migration initialise les tailles des documents existants.

Le budget de création ordinaire est de 5 demandes sur 10 secondes, 30 par minute
et 200 par heure, par compte. Il est commun aux créations personnelles et de
groupe, sans affecter l'autosauvegarde. Les diffusions, préparations d'année et
invitations ont leurs propres budgets nommés pour éviter les conflits de
compteurs entre opérations.

## Vérification des professeurs

La propriété d'une boîte email ne prouve pas un statut enseignant. Le domaine
scolaire sert à reconnaître une adresse et à orienter la personne. Il n'attribue
plus à lui seul le rôle professeur. Les rôles déjà attribués restent valides.

Le parcours opérationnel préparé est l'invitation par un référent dont le mandat
institutionnel a été vérifié par l'opérateur de Meriz. L'opérateur doit d'abord
créer une entrée `education_institutions` à partir des informations de
l'établissement, puis autoriser le référent avec :

```bash
php artisan meriz:education-referent personne@exemple.fr IDENTIFIANT_ETABLISSEMENT
php artisan meriz:education-referent personne@exemple.fr IDENTIFIANT_ETABLISSEMENT --revoke
```

Cette commande ne vérifie pas le mandat : elle enregistre une vérification déjà
faite auprès de l'établissement. Elle ne doit pas être utilisée sur la seule
déclaration d'une personne ou son domaine email. Aucun référent ni établissement
réel n'est créé par les migrations.

Un référent autorisé et dont l'email est confirmé peut adresser une invitation
nominative depuis son profil. Le code expire après 48 heures, est haché en base
et ne figure dans aucune URL. Le titulaire le saisit dans son profil. L'adresse
du compte doit correspondre au destinataire. L'activation professeur est
automatique dès que le code et l'email sont vérifiés, dans n'importe quel ordre.
Une adresse personnelle peut rester l'adresse de contact. Retirer un mandat
invalide les invitations non utilisées émises par ce référent.

Les connexions RENATER, eduGAIN, URCA et ENT/GAR ne sont pas raccordées. Le champ
`providers` de `GET /api/me/education` reste vide : aucune fausse connexion n'est
proposée. Leur activation nécessite l'admission du service, une intégration
testée et la vérification des attributs de statut transmis. EduConnect concerne
les élèves et responsables légaux, pas une attestation du statut enseignant.
Le rattachement institutionnel facultatif des élèves reste donc en attente.

Le lot 1.7.0 est publié sans ces raccordements, avec ce report explicitement
accepté. Les professeurs existants gardent leur rôle. Pour un nouveau professeur,
une invitation de référent vérifié permet le parcours automatique décrit plus
haut ; l'administration peut aussi attribuer le rôle après vérification.
La sélection « Professeur » à l'inscription ne constitue jamais cette preuve.

Références : [RENATER](https://services.renater.fr/federation/),
[eduGAIN](https://edugain.org/participants/how-to-use-edugain/),
[GAR](https://gar.education.fr/etablissements-et-ecoles/faq-accrochage-des-ressources-au-gar/).

## Préparation du déploiement

Sauvegarder la base et les médias avant les migrations. Déployer l'API avant
l'application : les nouveaux champs restent facultatifs pour les anciens clients,
mais le nouveau client attend la route de paramètres d'exercice. Utiliser les
procédures habituelles de configuration, cache et redémarrage de l'API.

La migration des tailles lit les contenus existants. Prévoir sa durée selon leur
volume. Le retour arrière doit tenir compte des nouvelles copies déjà créées :
ne pas exécuter un rollback destructeur sur des données utilisées sans sauvegarde.
