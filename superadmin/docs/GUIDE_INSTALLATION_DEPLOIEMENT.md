# Guide D'Installation Et De Déploiement

Dernière actualisation documentaire : 10 septembre 2026.

Ce guide installe le **modèle** sur une machine neuve. Pour une faculté
déjà copiée, voir le README de cette instance. Pour ouvrir une faculté :
[CREER_UN_SITE.md](CREER_UN_SITE.md).

Ne jamais copier un fichier contenant des secrets réels.

## Prérequis

- PHP 8.2 ou plus récent.
- Extensions PHP vérifiées par Composer : `ctype`, `dom`, `filter`, `intl`, `json`, `libxml`, `mbstring`, `phar`, `tokenizer`, `xmlwriter`.
- Extensions/runtime nécessaires au projet : `mysqli` pour MySQL/MariaDB et `fileinfo` pour la validation des uploads. `curl` est recommandé pour les environnements de déploiement usuels.
- Composer.
- MySQL ou MariaDB avec InnoDB et `utf8mb4`.
- Serveur web Apache ou Nginx pointant vers `public/`.

## Installation Locale

```bash
composer install
cp .env.example .env
php spark key:generate
```

Ne pas utiliser `cp env .env`. Le fichier `env` peut contenir des valeurs sensibles et ne doit pas servir de modèle.

Éditer ensuite `.env` :

```ini
CI_ENVIRONMENT = development
# Exemple démarrage unique du modèle ; en multi-dossiers utiliser 8101–8106
# (voir ARCHITECTURE.md / LOCAL_OPERATIONS.md).
app.baseURL = 'http://localhost:8080/'
app.defaultLocale = fr
app.appTimezone = Africa/Bujumbura
app.CSPEnabled = true
app.siteSlug = exemple
session.cookieName = ci_session_app
session.rememberCookieName = remember_app
# app.proxyIPs = 10.0.0.1,10.0.0.2
# app.uploadMirrors =  (facultaire : laisser vide)
# app.previewBase.exemple = http://localhost:8101/

database.default.hostname = localhost
database.default.database = ub_shared
database.default.username = ub_shared_user
database.default.password = change-this-local-password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Depuis un compte MySQL/MariaDB ayant les droits `CREATE USER` et `GRANT`, créer la base et un utilisateur SQL local. Adapter les mots de passe dans SQL puis reporter les mêmes valeurs dans `.env`.

```sql
CREATE DATABASE IF NOT EXISTS ub_shared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ub_shared_user'@'localhost' IDENTIFIED BY 'change-this-local-password';
GRANT ALL PRIVILEGES ON `ub_shared`.* TO 'ub_shared_user'@'localhost';
```

Exécuter les migrations et les données de démonstration :

```bash
php spark migrate --all
# (optionnel) php spark db:seed TemplateStarterSeeder — contenu de départ neutre
```

Conserver `--all` pour inclure les migrations de l'application et celles des packages, notamment CodeIgniter Shield.

Créer le premier superadministrateur :

```bash
php spark admin:create-superadmin
```

Lancer le serveur local :

```bash
php spark serve
```

Vérifier :

- `http://localhost:8101/` (fseg) · `:8102` (fsi) · `:8103` (superadmin)
  · `:8104` (med) · `:8105` (fabi) · `:8106` (flsh)

Lancer les instances : `scripts/dev-serve.sh start` (défaut : fseg, fsi,
superadmin). Les six sites :
`PLATFORM_INSTANCES='fseg fsi superadmin med fabi flsh' scripts/dev-serve.sh start`
(arrêt : `stop`, état : `status`). Opérations seed / cartes / admins :
[LOCAL_OPERATIONS.md](LOCAL_OPERATIONS.md). Le `.env` d'une instance locale contient :

```ini
app.baseURL = 'http://localhost:8101/'
app.allowedHostnames = localhost,127.0.0.1
```

## Commandes Spark Projet

- `php spark admin:create-superadmin` : crée un compte superadministrateur.
- `php spark app:production-check` : vérifie les prérequis production.
- `php spark app:production-check --strict` : considère aussi les avertissements comme bloquants.
- `php spark testdb:migrate` : migre la connexion `tests`.
- `php spark testdb:seed TemplateStarterSeeder` : charge les seeders sur la connexion `tests`.
- `php spark test` : lance la suite PHPUnit.

## Tests

Avant de lancer les tests, configurer une base dédiée :

```ini
database.tests.hostname = localhost
database.tests.database = platform_test
database.tests.username = platform_test_user
database.tests.password = change-this-test-password
database.tests.DBDriver = MySQLi
database.tests.DBPrefix = test_
database.tests.port = 3306
```

Attention : `tests/bootstrap.php` supprime et recrée `database.tests.database`. Ne jamais utiliser une base de développement ou production pour `database.tests.database`.

Le compte SQL de test doit pouvoir se connecter au serveur MySQL/MariaDB et recréer uniquement la base de test :

```sql
CREATE USER IF NOT EXISTS 'platform_test_user'@'localhost' IDENTIFIED BY 'change-this-test-password';
GRANT ALL PRIVILEGES ON `platform_test`.* TO 'platform_test_user'@'localhost';
```

Commande :

```bash
php spark test
```

## Déploiement Production

Configurer `.env` sur le serveur :

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://exemple.edu.bi/'
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
app.defaultLocale = fr
app.appTimezone = Africa/Bujumbura
app.siteSlug = exemple
encryption.key = valeur-secrete-generee
```

Base :

```ini
database.default.hostname = ...
database.default.database = ...
database.default.username = ...
database.default.password = ...
database.default.DBDriver = MySQLi
database.default.DBDebug = false
```

Installer les dépendances de production et appliquer les migrations sur la base configurée :

```bash
composer install --no-dev --optimize-autoloader
php spark migrate --all
```

Ne lancer `# (optionnel) php spark db:seed TemplateStarterSeeder — contenu de départ neutre` en production que pour une première installation qui doit recevoir les données de démonstration. Créer ensuite un compte administrateur avec `php spark admin:create-superadmin` si aucun superadministrateur n'existe.

Si les notifications de contact sont activées :

```ini
CONTACT_NOTIFICATION_ENABLED = true
CONTACT_NOTIFICATION_RECIPIENT = ...
CONTACT_NOTIFICATION_FROM_EMAIL = ...
CONTACT_NOTIFICATION_FROM_NAME = Faculte
CONTACT_NOTIFICATION_PROTOCOL = smtp
CONTACT_NOTIFICATION_SMTP_HOST = ...
CONTACT_NOTIFICATION_SMTP_PORT = 587
CONTACT_NOTIFICATION_SMTP_CRYPTO = tls
CONTACT_NOTIFICATION_SMTP_USER = ...
CONTACT_NOTIFICATION_SMTP_PASS = ...
```

Si le serveur est derrière un proxy ou un load balancer, renseigner `app.proxyIPs`
(liste d’IP ou JSON). `X-Forwarded-Host` n’est lu que dans ce cas.

### Checklist `.env` par instance

| Clé | Faculté (ex. `fseg`) | Superadministration |
| --- | --- | --- |
| `session.cookieName` | `ci_session_fseg` | `ci_session_central` |
| `session.rememberCookieName` | `remember_fseg` | `remember_central` |
| `app.uploadMirrors` | laisser vide (copie sœur `../{slug}/public` si présent) | `fseg:/chemin/fseg,fsi:/chemin/fsi` |
| `app.previewBase.{slug}` | inutile | ex. `http://localhost:8101/` pour l’aperçu local |
| `app.proxyIPs` | IPs du reverse proxy (liste à virgules), ou vide sans proxy | idem |

## Document Root

Le document root doit être :

```text
{chemin-du-projet}/public
```

Ne jamais exposer directement la racine du projet, `app/`, `writable/`, `vendor/` ou `.env`.

Le processus PHP doit pouvoir écrire dans `writable/` et dans les sous-dossiers d'uploads nécessaires sous `public/uploads/`.

## Apache

- Activer la réécriture d'URL.
- Pointer le vhost vers `public/`.
- Conserver `public/uploads/.htaccess`.
- Activer HTTPS.

## Nginx

- Pointer `root` vers `public/`.
- Utiliser `try_files $uri $uri/ /index.php?$query_string`.
- Inclure la protection `deploy/nginx/uploads-security.conf`.
- Activer HTTPS.

## Contrôle Avant Livraison

```bash
composer validate
php spark routes
php spark migrate:status
php spark app:production-check
```

`app:production-check --strict` est utile en préproduction ; il peut bloquer sur un avertissement volontairement facultatif, comme `proxyIPs` vide sur un serveur sans proxy. Documenter toute exception acceptée. Les actifs frontaux étant auto-hébergés, aucune dépendance CDN n'est à arbitrer.

## Sauvegardes Et Maintenance

- Sauvegarder la base avant toute migration en production.
- Sauvegarder `public/uploads/`.
- Surveiller `/healthz`.
- Surveiller les logs dans `writable/logs/`.
- Garder Composer et les dépendances à jour après test en recette.
