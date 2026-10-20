# Fréquentation de Meriz

La page `/admin/traffic`, réservée aux administrateurs, lit des rapports JSON
privés produits par GoAccess. Aucun script de suivi n'est ajouté au navigateur.
Les filtres proposent la vitrine, l'application, l'API et l'historique commun.

## Collecte

Le serveur utilise nginx, pas Apache. Avant le 20 octobre 2026, le journal
commun n'indiquait pas le domaine : impossible d'attribuer sûrement ces
requêtes à un site. Cet historique reste identifié comme tel.

Chaque bloc HTTPS a désormais une directive dédiée :

```nginx
access_log /var/log/nginx/meriz-site.access.log combined;
access_log /var/log/nginx/meriz-app.access.log combined;
access_log /var/log/nginx/meriz-api.access.log combined;
```

Installer `goaccess` sur le VPS. Installer `deploy/traffic-report.py`, appartenant
à root et exécutable, sous `/usr/local/sbin/meriz-traffic-report`. Le cron root
`/etc/cron.d/meriz-traffic` le lance toutes les cinq minutes :

```cron
*/5 * * * * root /usr/bin/python3 /usr/local/sbin/meriz-traffic-report > /var/log/meriz-traffic.log 2>&1
```

Le script lit les journaux courants et leurs rotations, sans double cumul.
Il écrit atomiquement dans `storage/app/private/traffic`, fichiers root:www-data
0640, dossier 0750. Le processus PHP peut les lire mais ne lit jamais les logs
système. Aucune route publique n'expose ces JSON.

Les paramètres d'URL et les identifiants sensibles sont retirés avant analyse.
Les panneaux d'adresses IP et de référents complets sont désactivés. Seuls les
agrégats utilisés par l'administration sont conservés dans les rapports.

## Pays

DB-IP Country Lite est téléchargé depuis la page officielle, stocké sous
`/var/lib/meriz-traffic/country.mmdb` et actualisé après 25 jours. Le calcul est
local : aucune IP de visiteur n'est communiquée au fournisseur. L'attribution
DB-IP (CC BY 4.0) est affichée dans le rapport et les mentions légales.
Un échec de mise à jour garde la base précédente ; son âge reste visible.

## Interprétation

Les visiteurs sont estimés par IP, navigateur et jour. Ils ne représentent ni
des personnes uniques ni des sessions authentifiées. Les robots reconnus sont
exclus, mais certains robots peuvent rester. Les VPN peuvent fausser les pays.
Les requêtes comprennent notamment les appels API et les erreurs techniques.
Les vues internes de la SPA ne produisent pas de requête HTML supplémentaire.

Les filtres 1, 7 et 30 jours sont limités aux logs effectivement disponibles.
Les journées sont calculées dans le fuseau Europe/Paris.
La rotation nginx conserve actuellement 14 archives quotidiennes et le fichier
courant. Les dates couvertes figurent dans chaque rapport. Les rapports sont
recalculés, pas cumulés : une actualisation ne gonfle jamais les compteurs.
