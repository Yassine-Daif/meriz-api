# Sauvegardes et restauration

Ce qui est sauvegardé, comment, et surtout **comment on le restaure**.
Une sauvegarde qu'on ne sait pas restaurer ne vaut rien.

## Ce qui est gardé

| Quoi | Fichier déposé | Pourquoi |
| --- | --- | --- |
| la base | `AAAA-MM-JJ-base.sql.gz` | comptes, classes, devoirs, rendus, commentaires |
| `storage/app/private` | `AAAA-MM-JJ-prive.tar.gz` | images des devoirs et médias des cours |

Dépôt : `/var/backups/meriz`, accessible au seul propriétaire.
Rétention : **14 jours** glissants.
Déclenchement : `/etc/cron.d/meriz-sauvegarde`, chaque nuit à 3 h 20.
Script : `/usr/local/bin/meriz-sauvegarde`.

Les identifiants de base ne sont **pas** écrits dans le script : il les
lit dans le `.env` de l'application.

Un `journal.txt` note chaque passage avec la taille obtenue. Un vidage
de moins d'un kilo-octet fait échouer le script plutôt que d'écraser une
bonne sauvegarde par une mauvaise, et le fichier n'est renommé qu'une
fois écrit en entier : une sauvegarde interrompue reste en `.en-cours`
et ne sera jamais prise pour bonne.

## Restaurer la base

**Sur une base d'essai d'abord**, jamais directement sur la production :

```bash
BASE=meriz ; UTIL=meriz
sudo mysql -e "CREATE DATABASE ${BASE}_essai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
               GRANT ALL ON ${BASE}_essai.* TO '$UTIL'@'localhost';"
gunzip -c /var/backups/meriz/AAAA-MM-JJ-base.sql.gz | mysql -u $UTIL -p ${BASE}_essai
```

Puis on compare le nombre de tables et de lignes avec la production.
Si tout concorde, on peut remplacer la production :

```bash
sudo systemctl stop php8.5-fpm          # personne n'écrit pendant ce temps
mysql -u meriz -p meriz < <(gunzip -c /var/backups/meriz/AAAA-MM-JJ-base.sql.gz)
sudo systemctl start php8.5-fpm
```

## Restaurer les fichiers privés

```bash
cd /var/www/meriz-api/storage/app
sudo tar xzf /var/backups/meriz/AAAA-MM-JJ-prive.tar.gz
sudo chown -R ubuntu:www-data private
```

## L'épreuve, et sa date

La restauration a été **éprouvée pour de vrai le 17 octobre 2026** : le
vidage du jour a été restauré dans une base d'essai séparée, les 20
tables et les lignes de chaque table concordaient avec la production,
l'archive des fichiers privés se relisait, et la base d'essai a été
supprimée ensuite. La production n'a pas été touchée.

Le script `/tmp/essai-restauration.sh` employé ce jour-là fait cette
vérification de bout en bout. À rejouer après tout changement du schéma.

## Ce que ces sauvegardes ne couvrent pas

Elles vivent **sur le même serveur** que la production. Si la machine est
perdue, elles le sont avec. Une copie vers un stockage distant reste à
mettre en place : c'est elle qui protège d'une panne matérielle ou d'une
erreur d'hébergeur, pas celle-ci.
