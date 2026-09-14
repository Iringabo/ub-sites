# Guide d’installation — instance facultaire

Dernière actualisation : 10 septembre 2026.

Cette instance **partage la base** avec les autres facultés et la
superadministration. Ne créez pas une base privée. Recopiez
`database.default.*` depuis un dossier déjà en service.

Pour ouvrir une *nouvelle* faculté : dossier `template/`, guide
`template/docs/CREER_UN_SITE.md`.

Ne jamais copier un `.env` réel.

## Prérequis

PHP 8.2+, Composer, MySQL/MariaDB InnoDB `utf8mb4`, Apache ou Nginx pointant
vers `public/`. Extensions : `mysqli`, `fileinfo`, `intl`, `mbstring`, plus
celles exigées par Composer.

## Installation locale

```bash
composer install
cp .env.example .env
php spark key:generate
```

Dans `.env` :

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8101/'
app.siteSlug = fseg
app.centralAdminMode = false
session.cookieName = ci_session_fseg
app.defaultLocale = fr
app.appTimezone = Africa/Bujumbura
app.CSPEnabled = true
app.allowedHostnames = localhost,127.0.0.1

database.default.hostname = localhost
database.default.database = ub_shared
database.default.username = ub_shared_user
database.default.password = change-this-local-password
database.default.DBDriver = MySQLi
```

Adaptez le port, le slug et le cookie à **cette** faculté. Les identifiants
SQL doivent être **les mêmes** que fsi et superadmin.

Les migrations se jouent **une fois** pour la plateforme :

```bash
php spark migrate --all
```

Lancer les instances :

```bash
./scripts/dev-serve.sh start
./scripts/dev-serve.sh status
./scripts/dev-serve.sh stop
```

- Public : `http://localhost:8101/` (FSEG) · `:8102` (FSI)
- Administration : `/admin`
- Santé : `/healthz`

## Tests

Base **dédiée**, distincte de `ub_shared` : `tests/bootstrap.php` la
supprime et la recrée.

```ini
database.tests.database = platform_test
```

```bash
php spark test
```

## Production

```ini
CI_ENVIRONMENT = production
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
database.default.DBDebug = false
```

Document root : `{projet}/public`. Écrire dans `writable/` et
`public/uploads/`. HTTPS. `php spark migrate --all` une fois sur la base
partagée. `php spark app:production-check`.

Ne pas lancer de seeder de démonstration sur un site déjà rédigé.

## Apache / Nginx

Réécriture vers `public/index.php`. Conserver
`public/uploads/.htaccess` ou `deploy/nginx/uploads-security.conf`.

## Sauvegardes

Base partagée et `public/uploads/` avant chaque migration. Logs :
`writable/logs/`. Surveiller `/healthz`.
