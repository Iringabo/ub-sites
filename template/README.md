# Plateforme Web Multi-Facultés UB — Modèle

Ce dépôt est le **modèle (template)** de la plateforme : une application
CodeIgniter 4 que l'on copie et configure pour créer chaque site facultaire
(site public + administration dans le même dossier) ainsi que l'instance
superadministration. Une seule base de données est partagée ; l'isolation des
contenus est assurée par `site_id`. Voir
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) pour l'état actuel.

## Fonctionnalités

- Site public : accueil, faculté, formations, recherche, personnel, actualités, événements, alumni et contact.
- Back-office : tableau de bord, contenus d'accueil, carrousel, atouts, statistiques, pages, programmes, personnel, recherche, alumni, messages, utilisateurs, paramètres et sites facultaires.
- Multi-site : contenus isolés par `site_id`, sites dans `sites`, affectations administrateurs dans `user_sites`.
- Instance superadministration : dossier dédié (`app.centralAdminMode=true`) réservé au groupe `superadmin`, tableau de bord sur toutes les facultés.
- Administration facultaire : `/admin` de chaque dossier facultaire, strictement limitée au personnel affecté à ce site.
- Bilinguisme : français par défaut, anglais disponible côté public, administration et authentification en français.
- Sécurité : Shield, permissions, CSRF, secure headers, CSP configurée, validation serveur, uploads contrôlés.

## Stack

- PHP `^8.2`
- CodeIgniter 4.7.x
- CodeIgniter Shield 1.3.x
- MySQL/MariaDB avec InnoDB et `utf8mb4`
- Bootstrap 5, Bootstrap Icons
- PHPUnit 10

## Architecture Succincte

- Routes explicites : `app/Config/Routes.php`
- Contrôleurs publics : `app/Controllers/`
- Contrôleurs admin : `app/Controllers/Admin/`
- Modèles : `app/Models/`
- Services métier : `app/Services/`
- Migrations/seeders : `app/Database/`
- Vues publiques et admin : `app/Views/`
- Langues : `app/Language/fr/` et `app/Language/en/`
- Assets et uploads : `public/`

## Quick Start (développement local)

```bash
composer install
cp .env.example .env
php spark key:generate
# Renseigner database.default.* dans .env, puis :
php spark migrate --all
php spark site:create --identifier demo --slug demo --name "Faculté de démonstration"
php spark admin:create-superadmin --email admin@example.test --username admin
php spark serve
```

Vérifications rapides :

- Public : `http://localhost:8080/` (contenu de départ neutre)
- Administration : `http://localhost:8080/admin`
- Santé : `http://localhost:8080/healthz`
- Créer une instance réelle (faculté ou superadmin) :
  [docs/TEMPLATE_SETUP.md](docs/TEMPLATE_SETUP.md)

Ne copiez pas le fichier `.env` d'une instance pour initialiser l'environnement local. Utilisez `.env.example`.

## Tests

```bash
composer validate
php spark routes
php spark migrate:status
php spark test
```

Attention : `php spark test` charge `tests/bootstrap.php`, qui supprime et recrée la base `database.tests.database`. Lire [tests/README.md](tests/README.md) avant de lancer la suite.

## Production

Le serveur web doit exposer uniquement `public/`. Avant livraison, configurer au minimum :

- `CI_ENVIRONMENT=production`
- `app.baseURL` en HTTPS
- `app.forceGlobalSecureRequests=true`
- `app.CSPEnabled=true`
- `encryption.key`
- base MySQL/MariaDB réelle avec `DBDebug=false`
- SMTP si les notifications de contact sont activées
- `app.proxyIPs` si l'application est derrière un proxy

La commande `php spark app:production-check` vérifie les prérequis principaux. Avec `--strict`, les avertissements facultatifs deviennent bloquants.

## Documentation

L'index complet est dans [docs/README.md](docs/README.md).

- Architecture actuelle : [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Créer une instance : [docs/TEMPLATE_SETUP.md](docs/TEMPLATE_SETUP.md)
- Installation : [docs/GUIDE_INSTALLATION_DEPLOIEMENT.md](docs/GUIDE_INSTALLATION_DEPLOIEMENT.md)
- Administration : [docs/GUIDE_ADMINISTRATEUR.md](docs/GUIDE_ADMINISTRATEUR.md)
- Utilisation publique : [docs/GUIDE_UTILISATEUR_CLIENT.md](docs/GUIDE_UTILISATEUR_CLIENT.md)
- Multi-site : [docs/ARCHITECTURE_MULTI_SITES.md](docs/ARCHITECTURE_MULTI_SITES.md)
- Multi-dossiers : [docs/MULTI_FOLDER_DEPLOYMENT.md](docs/MULTI_FOLDER_DEPLOYMENT.md)
- Bilinguisme : [docs/ARCHITECTURE_BILINGUE.md](docs/ARCHITECTURE_BILINGUE.md)
- Base de données : [docs/05-BASE-DE-DONNEES.md](docs/05-BASE-DE-DONNEES.md)
- Sécurité : [docs/SECURITY_REVIEW.md](docs/SECURITY_REVIEW.md)

## Sécurité Des Secrets

`.env.example` contient des placeholders sûrs. `.env` est ignoré par Git et
ne doit jamais être recopié d'une instance vers une autre ; les secrets qui
ont circulé doivent être renouvelés.
