# Discussions et annotations

Les commentaires restent rattachés à un document et passent par les routes
existantes `/api/documents/{document}/comments` et `/api/comments/{comment}`.
Le modèle Merise et son format de fichier ne changent pas.

La création accepte deux champs facultatifs :

- `parent_id` : identifiant d'une discussion du même document. Un seul niveau
  de réponses est autorisé. Une réponse rouvre la discussion.
- `drawing` : annotation conservée, tableau de 2 à 64 points `{x, y}`. Les
  coordonnées sont bornées par `comments.position_bound` et normalisées en
  nombres. Une annotation ne peut pas être une réponse ni avoir une position
  de bulle en plus de ses points.

Les anciens commentaires gardent ces deux champs à `null`. Le corps reste
obligatoire. Pour un trait, l'application envoie « Annotation au crayon ».
Le quota de commentaires et la limitation de débit existants s'appliquent
aussi aux réponses et aux annotations conservées.

La ressource renvoie également `can_delete`, calculé par la Policy pour le
lecteur. L'application s'en sert pour proposer l'effacement. Le serveur
revérifie toujours le droit lors de la suppression : auteur, prof du devoir
ou créateur du groupe. Les autres membres peuvent masquer les traits sur
leur propre écran. Supprimer une discussion supprime ses réponses.

Les champs sont inclus dans l'export des données du titulaire du compte.
Les annotations des autres ne deviennent pas ses données personnelles.

## Transport et observation

Les traits en cours et ceux de courte durée restent dans la présence du
canal existant. Ils ne sont pas enregistrés par cette API. L'application
affiche les traits chez leur auteur et chez leurs partenaires. Seul le mode
« Conserver » crée une annotation sur le serveur.

Après une écriture de commentaire, le client signale `comments-changed` sur
le même canal pour demander une relecture. Ce signal ne porte aucun contenu
et n'accorde aucun droit. L'API reste la source des discussions. Un
rafraîchissement périodique sert de secours.

L'observation seule reste sans édition et sans présence affichée. Le passage
en correction à deux conserve le document ouvert. Le client attend la
synchronisation et la présence de l'élève avant de permettre au prof de
modifier le modèle. L'élève détecte aussi l'activation ou l'arrêt du suivi
sur un travail déjà ouvert.

La migration ajoute uniquement des colonnes facultatives. En production,
appliquer la migration avant de publier l'application qui les utilise.
