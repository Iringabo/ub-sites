# Guide d’installation — superadministration

Dernière actualisation : 10 septembre 2026.

Cette instance **pilote** les facultés déjà créées. Elle partage la **même
base** que fseg et fsi. Ne créez pas une base privée.

On n’y crée pas de site. Procédure : `template/docs/CREER_UN_SITE.md`.

Ne jamais copier un `.env` réel.

## Prérequis

PHP 8.2+, Composer, MySQL/MariaDB InnoDB `utf8mb4`, Apache ou Nginx pointant
vers `public/`.

## Installation locale

```bash
composer install
cp .env.example .env
php spark key:generate
```

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8103/'
app.centralAdminMode = true
session.cookieName = ci_session_central
app.CSPEnabled = true

database.default.hostname = localhost
database.default.database = ub_shared
database.default.username = ub_shared_user
database.default.password = change-this-local-password
database.default.DBDriver = MySQLi
```

Mêmes identifiants SQL que les facultés.

```bash
php spark migrate --all
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-superadmin \
    --email admin@example.edu --username admin
./scripts/dev-serve.sh start
```

- Administration : `http://localhost:8103/admin`
- L’accueil public redirige vers `/admin`

## Tests

Base de test dédiée. `php spark test` la recrée.

## Production

Document root : `{projet}/public`. HTTPS. Compte du groupe `superadmin`
uniquement. `php spark app:production-check`.

## Sauvegardes

La base est celle des facultés : une sauvegarde couvre tout le contenu.
