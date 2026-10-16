# Signaler une faille de sécurité

Merci de prendre le temps de le faire. Meriz traite des données scolaires, et
souvent celles de mineurs : une faille ici n'est pas un détail.

## N'ouvre pas d'issue publique

Une issue est lisible par tout le monde, immédiatement. Décrire une faille dans
une issue, c'est donner le mode d'emploi à n'importe qui pendant tout le temps
qu'il faudra pour corriger. Même pour une faille qui te semble mineure, passe
par le canal privé.

## Le canal privé

**[Ouvrir un signalement privé sur GitHub](https://github.com/Yassine-Daif/meriz-api/security/advisories/new)**

Onglet `Security` du dépôt, puis `Report a vulnerability`. L'échange reste
privé entre toi et le mainteneur jusqu'à ce qu'un correctif existe. Aucune
adresse email n'est exposée ici, donc rien à moissonner.

<!--
    Porteur du projet : si tu veux offrir un second canal, remplace cette
    ligne par un contact de ton choix. À toi de le renseigner, je n'en
    invente pas.

    Contact : …
-->

## Ce qu'un bon signalement contient

Pas besoin d'un rapport formel. Ces quatre éléments suffisent à aller vite :

1. **Ce qu'on obtient** : lire les documents d'un autre élève, passer prof sans
   adresse scolaire, entrer dans `/admin`…
2. **Comment le reproduire** : les requêtes, dans l'ordre. Un `curl` est
   parfait.
3. **Où** : la route, le fichier, ou le commit sur lequel tu as travaillé.
4. **Ce que tu en déduis**, si tu as une idée de la cause ou du correctif.

## Ce à quoi tu peux t'attendre

- Un accusé de réception, et des questions si quelque chose n'est pas clair.
- Un échange pendant la correction, et la possibilité de relire le correctif.
- Un crédit à ton nom dans l'advisory publié, si tu le souhaites — ou l'anonymat
  si tu préfères.

Meriz est porté par une personne, pas par une équipe d'astreinte : pas de délai
promis qui ne serait pas tenu. Les failles qui exposent des données d'élèves
passent avant tout le reste.

## Le périmètre

Le projet n'a pas encore de version publiée. La référence est la branche `main`
du dépôt : c'est sur elle que les correctifs partent.

Sont dans le périmètre : ce serveur, son API, sa zone d'administration, ses
canaux temps réel.

En revanche, ce n'est pas une faille du produit :

- une alerte sur une dépendance de **développement**, exploitable seulement
  pendant la construction, sans chemin jusqu'à un utilisateur ;
- un comportement d'un serveur mal configuré au déploiement — la liste des
  réglages qui comptent est dans [docs/deploiement.md](docs/deploiement.md) ;
- une limite déjà connue et écrite dans [docs/securite.md](docs/securite.md).
  Ce document dit ce qui est protégé, comment, et ce qui est assumé : commence
  par là, tu verras peut-être que ton point y est déjà traité, ou au contraire
  qu'il ouvre une piste.

Et si tu n'es pas sûr que c'en soit une : signale quand même. Trancher, c'est
notre travail, pas le tien.
