# Guide D'Installation Et De Déploiement

Dernière actualisation documentaire : 11 août 2026.

Ce guide permet d'installer l'application sur une machine neuve. Ne jamais copier un fichier contenant des secrets réels depuis un autre environnement.

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
app.baseURL = 'http://localhost:8080/'
app.defaultLocale = fr
app.appTimezone = Africa/Bujumbura
app.CSPEnabled = true
app.siteSlug = fseg

database.default.hostname = localhost
database.default.database = fseg
database.default.username = fseg_user
database.default.password = change-this-local-password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Depuis un compte MySQL/MariaDB ayant les droits `CREATE USER` et `GRANT`, créer la base et un utilisateur SQL local. Adapter les mots de passe dans SQL puis reporter les mêmes valeurs dans `.env`.

```sql
CREATE DATABASE IF NOT EXISTS fseg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'fseg_user'@'localhost' IDENTIFIED BY 'change-this-local-password';
GRANT ALL PRIVILEGES ON `fseg`.* TO 'fseg_user'@'localhost';
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

Lancer les trois instances d'un coup : `scripts/dev-serve.sh start`
(arrêt : `stop`, état : `status`). Le `.env` d'une instance locale contient :

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
database.tests.database = fseg_test
database.tests.username = fseg_test_user
database.tests.password = change-this-test-password
database.tests.DBDriver = MySQLi
database.tests.DBPrefix = test_
database.tests.port = 3306
```

Attention : `tests/bootstrap.php` supprime et recrée `database.tests.database`. Ne jamais utiliser une base de développement ou production pour `database.tests.database`.

Le compte SQL de test doit pouvoir se connecter au serveur MySQL/MariaDB et recréer uniquement la base de test :

```sql
CREATE USER IF NOT EXISTS 'fseg_test_user'@'localhost' IDENTIFIED BY 'change-this-test-password';
GRANT ALL PRIVILEGES ON `fseg_test`.* TO 'fseg_test_user'@'localhost';
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
app.siteSlug = fseg
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
CONTACT_NOTIFICATION_FROM_NAME = FSEG
CONTACT_NOTIFICATION_PROTOCOL = smtp
CONTACT_NOTIFICATION_SMTP_HOST = ...
CONTACT_NOTIFICATION_SMTP_PORT = 587
CONTACT_NOTIFICATION_SMTP_CRYPTO = tls
CONTACT_NOTIFICATION_SMTP_USER = ...
CONTACT_NOTIFICATION_SMTP_PASS = ...
```

Si le serveur est derrière un proxy ou un load balancer, configurer `app.proxyIPs` selon l'infrastructure et s'assurer que seul ce proxy peut fournir ou réécrire `X-Forwarded-Host`.

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
