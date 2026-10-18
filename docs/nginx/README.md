# Les blocs nginx de la production

Copie des trois blocs servis en production, pour qu'ils survivent à la
perte du serveur. Ils sont relevés tels quels, sans rien de sensible :
les certificats y figurent par leur **chemin**, jamais par leur contenu.

| Fichier | Domaine | Racine |
| --- | --- | --- |
| `meriz-site.conf` | `meriz.fr`, et `www` qui y renvoie | `/var/www/meriz-site` |
| `meriz-app.conf` | `app.meriz.fr` | `/var/www/meriz-app` |
| `meriz-api.conf` | `api.meriz.fr` | `/var/www/meriz-api/public` |

## Remettre en place sur un serveur neuf

```bash
sudo cp meriz-*.conf /etc/nginx/sites-available/
for f in meriz-site meriz-app meriz-api; do
  sudo ln -sf /etc/nginx/sites-available/$f /etc/nginx/sites-enabled/$f
done
sudo nginx -t && sudo systemctl reload nginx
```

Les certificats sont à refaire avant, sinon nginx refuse de démarrer :

```bash
sudo certbot --nginx -d meriz.fr -d www.meriz.fr -d app.meriz.fr -d api.meriz.fr
```

## Deux points à ne pas perdre en route

**Le repli SPA de l'application.** `app.meriz.fr` rend `index.html` pour
toute adresse inconnue. Sans lui, les liens de confirmation d'adresse et
de réinitialisation de mot de passe, qui portent un chemin, tomberaient
en 404 : ce sont des liens de courriel, on ne les rattrape pas.

**Le défi de Let's Encrypt.** La redirection vers HTTPS est posée dans un
emplacement, pas au niveau du serveur. Un `return` de serveur s'exécute
avant la recherche d'emplacement et emporterait le défi avec lui, donc
les renouvellements échoueraient.
