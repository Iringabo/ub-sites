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
app.baseURL = 'http://localhost:8102/'
app.siteSlug = fsi
app.centralAdminMode = false
session.cookieName = ci_session_fsi
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

Les identifiants SQL doivent être **les mêmes** que fseg et superadmin.

```bash
php spark migrate --all
./scripts/dev-serve.sh start
```

- Public : `http://localhost:8102/`
- Administration : `/admin`
- Santé : `/healthz`

## Tests

`tests/bootstrap.php` recrée `database.tests.database`. Ne jamais pointer
cette variable vers la base partagée.

```bash
php spark test
```

## Production

Document root : `{projet}/public`. HTTPS.
`php spark migrate --all` une fois. `php spark app:production-check`.

## Apache / Nginx

Réécriture vers `public/index.php`. Conserver la protection des uploads.

## Sauvegardes

Base partagée et `public/uploads/` avant chaque migration.
