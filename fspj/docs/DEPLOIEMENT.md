# Installation et déploiement

Ce guide installe une instance (faculté ou superadministration) sur une
machine neuve, puis la met en production sur un serveur dédié ou un
hébergement mutualisé cPanel. Toutes les instances partagent **une seule
base** ; chaque site est **un dossier** avec son propre `public/`.

Les guides par hébergeur et la checklist de mise en ligne de la plateforme
sont dans le dossier `deploy/` à la racine du dépôt
(`deploy/README.md`, `deploy/09-production-go-live.md`).

## 1. Prérequis

- PHP 8.2 ou plus, avec `intl`, `mbstring`, `mysqli`, `fileinfo`, `gd`,
  `json`, `dom`, `xmlwriter`, `ctype`, `tokenizer` (et `curl`
  recommandé).
- Composer.
- MySQL ou MariaDB (InnoDB, `utf8mb4`).
- Apache ou Nginx, avec le document root sur `public/` **uniquement**.

## 2. Base de données

Depuis un compte MySQL autorisé à créer des utilisateurs :

```sql
CREATE DATABASE IF NOT EXISTS ub_shared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ub_shared_user'@'localhost' IDENTIFIED BY 'mot-de-passe-a-changer';
GRANT ALL PRIVILEGES ON `ub_shared`.* TO 'ub_shared_user'@'localhost';
```

Pour les tests, créer une **autre** base dédiée (voir
[tests/README.md](../tests/README.md)) : `tests/bootstrap.php` la supprime et
la recrée à chaque exécution.

## 3. Installer une instance

```bash
composer install            # --no-dev --optimize-autoloader en production
cp .env.example .env        # jamais le .env d’une autre instance
php spark key:generate
```

### Réglages `.env`

| Clé | Développement | Production |
|---|---|---|
| `CI_ENVIRONMENT` | `development` | `production` |
| `app.baseURL` | `http://localhost:8101/` (un port par instance) | `https://fseg.ub.edu.bi/` |
| `app.forceGlobalSecureRequests` | `false` | `true` |
| `app.CSPEnabled` | `true` | `true` |
| `app.appTimezone` | `Africa/Bujumbura` | idem |
| `app.allowedHostnames` | `localhost,127.0.0.1` | hôtes publics de l’instance |
| `app.siteSlug` | slug de la faculté (vide sur `superadmin/`) | idem |
| `app.centralAdminMode` | `false`, ou `true` sur `superadmin/` | idem |
| `app.centralAdminHosts` | vide | hôte de la superadministration |
| `app.requireKnownHostname` | `false` | `true` pour refuser les hôtes inconnus |
| `app.proxyIPs` | vide | IP du reverse proxy ou du load balancer |
| `session.cookieName` | `ci_session_{slug}` ou `ci_session_central` | idem |
| `session.rememberCookieName` | `remember_{slug}` ou `remember_central` | idem |
| `app.uploadMirrors` | `superadmin/` seulement : `fseg:/chemin/fseg,…` | idem |
| `database.default.*` | base partagée | base partagée, `DBDebug = false` |
| `encryption.key` | généré par `key:generate` | secret propre à l’instance |

Notifications du formulaire de contact (facultatives) :
`CONTACT_NOTIFICATION_ENABLED`, `CONTACT_NOTIFICATION_RECIPIENT`,
`CONTACT_NOTIFICATION_FROM_EMAIL`, `CONTACT_NOTIFICATION_FROM_NAME`,
`CONTACT_NOTIFICATION_PROTOCOL` (`smtp`) et `CONTACT_NOTIFICATION_SMTP_*`
(hôte, port, chiffrement, utilisateur, mot de passe).

### Initialiser

```bash
php spark migrate --all     # une seule fois pour toute la plateforme
```

`--all` inclut les migrations de Shield et de Settings. La création d’une
faculté (`site:create`) et des premiers comptes est décrite dans le guide
de création de site des mainteneurs du modèle. Commandes de compte :

- `php spark admin:create-faculty-admin` : dossier facultaire uniquement
  (`app.centralAdminMode=false`, `app.siteSlug` renseigné) ;
- `php spark admin:create-superadmin` : dossier `superadmin/` uniquement.

Les mots de passe se passent par variable d’environnement
(`--password-env PLATFORM_ADMIN_PASSWORD`), jamais en clair dans la
commande.

## 4. Serveur web

Document root : `{dossier de l’instance}/public`. Ne jamais exposer la
racine, `app/`, `writable/`, `vendor/` ni `.env`. PHP doit pouvoir écrire
dans `writable/` et `public/uploads/`.

**Nginx :**

```nginx
server {
    listen 443 ssl;
    server_name fseg.ub.edu.bi;
    root /var/www/fseg/public;
    index index.php;

    include /var/www/deploy/nginx/uploads-security.conf;

    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    }
}
```

**Apache :** réécriture d’URL activée, vhost sur `public/`, conserver
`public/.htaccess` et `public/uploads/.htaccess`.

Un hôte par faculté, chacun vers le `public/` de **son** dossier ; la
superadministration a son propre hôte, hors du document root des facultés.

## 5. Contrôles avant livraison

```bash
composer validate
php spark routes
php spark migrate:status
php spark app:production-check          # --strict en préproduction
```

`app:production-check` vérifie l’environnement, HTTPS, la CSP, la clé de
chiffrement, le débogage base, la colonne `settings.site_id` et les
réglages de proxy. Avec `--strict`, les avertissements bloquent aussi
(par exemple `proxyIPs` vide sur un serveur sans proxy : documenter
l’exception).

Tester ensuite dans le navigateur : accueil, `/admin`, formulaire de
contact, envoi d’une image et d’un PDF depuis l’administration, `/healthz`
(réponse 204), version anglaise (`?lang=en`).

## 6. Sauvegardes et maintenance

- Sauvegarder la base avant toute migration, et `public/uploads/` de chaque
  instance.
- Conserver chaque `.env` dans un coffre de secrets, jamais dans Git.
- Surveiller `/healthz` et `writable/logs/`.
- Mettre à jour Composer et les dépendances après test en recette.

## Annexe : hébergement mutualisé cPanel

1. **Prérequis** : PHP 8.2+, les extensions ci-dessus, MySQL, HTTPS
   (AutoSSL), si possible un terminal SSH. Sans Composer sur l’hébergement,
   lancer `composer install --no-dev --optimize-autoloader` en local et
   téléverser aussi `vendor/`.
2. **Structure** : placer chaque instance hors du web root
   (`/home/compte/fseg-app/`) et pointer le domaine ou le sous-domaine vers
   `/home/compte/fseg-app/public`. Une instance par sous-domaine ; jamais
   plusieurs facultés sur le même `public/`.
3. **Si le document root ne peut pas changer** : copier le contenu de
   `public/` dans `public_html`, puis remplacer dans `public_html/index.php`
   la ligne `require FCPATH . '../app/Config/Paths.php';` par le chemin
   absolu `require '/home/compte/fseg-app/app/Config/Paths.php';`.
4. **Base** : créer la base et l’utilisateur avec l’assistant MySQL de
   cPanel (le nom reçoit le préfixe du compte) ; toutes les instances
   utilisent la même base.
5. **`.env`** : créer un fichier neuf à partir de `.env.example` (tableau
   ci-dessus). Si `spark` n’est pas disponible, générer `encryption.key` en
   local et ne copier que cette valeur.
6. **Migrations** : `php spark migrate --all` dans le terminal cPanel. Sans
   terminal, préparer la base en local et importer un dump avec
   phpMyAdmin.
7. **Permissions** : `chmod -R 775 writable public/uploads` (éviter `777`).
8. **Hôtes** : l’hôte public de chaque faculté est enregistré par
   `site:create` (`--hostname` ou `app.publicHostPattern`). Dans chaque
   `.env`, renseigner `app.allowedHostnames`, et sur la superadministration
   `app.centralAdminHosts`.

| Symptôme | Cause probable | Correction |
|---|---|---|
| Erreur 500 | version de PHP, extension manquante, permissions, `.env` invalide | vérifier PHP, `writable/logs/`, les extensions |
| 404 sur toutes les pages sauf l’accueil | `.htaccess` absent ou réécriture désactivée | conserver `public/.htaccess`, activer la réécriture |
| Erreur de base de données | identifiants ou préfixe cPanel incorrects | vérifier hôte, nom préfixé, droits |
| CSS ou JS manquants | mauvais document root ou copie incomplète | pointer vers `public/` |
| Téléversement impossible | `public/uploads/` non inscriptible ou quota | corriger permissions et quota |
| Site introuvable (404) | `app.siteSlug` ou hôte inconnu | vérifier le slug et les hôtes du site |
