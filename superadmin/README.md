# Plateforme des sites facultaires — Université du Burundi

Application CodeIgniter 4 qui sert le site public bilingue (français,
anglais) d’une faculté et son administration (`/admin`). Chaque faculté,
ainsi que la superadministration, est un dossier séparé ; tous partagent
**une seule base de données** où les contenus sont isolés par `site_id`.
Le code commun vit dans le dossier modèle `template/` et se recopie dans
chaque dossier.

## Rôles

| Rôle | Où il se connecte | Ce qu’il fait |
|---|---|---|
| Éditeur | `/admin` de sa faculté | contenus des pages et listes |
| Administrateur de faculté | `/admin` de sa faculté | contenus, messages, paramètres du site, comptes administrateur et éditeur de **sa** faculté |
| Superadministrateur | superadministration | choisit une faculté et en modifie le contenu ; crée les comptes de faculté ; se crée uniquement en ligne de commande |

Aucune faculté ne se crée depuis l’écran d’administration.

## Lancer en local

```bash
composer install
cp .env.example .env        # ne jamais copier le .env d’une autre instance
php spark key:generate
# Renseigner database.default.*, app.baseURL, app.siteSlug, session.cookieName
php spark migrate --all
php spark serve
```

- Site public : `http://localhost:8080/`
- Administration : `http://localhost:8080/admin`
- Santé : `http://localhost:8080/healthz` (réponse 204)

Sur la plateforme complète, `./scripts/dev-serve.sh start` lance toutes les
instances (ports 8101 à 8106) : voir
[docs/OPERATIONS_LOCALES.md](docs/OPERATIONS_LOCALES.md).

## Tests

```bash
php spark testdb:migrate
php vendor/bin/phpunit --configuration phpunit.dist.xml --no-logging tests/unit/HealthTest.php
```

Les tests utilisent la base `database.tests.database`, recréée par le
bootstrap : lire [tests/README.md](tests/README.md) avant de lancer la
suite.

## Documentation

| Document | Pour qui | Contenu |
|---|---|---|
| [docs/GUIDE_ADMINISTRATEUR.md](docs/GUIDE_ADMINISTRATEUR.md) | éditeurs, administrateurs | utiliser `/admin`, choisir une page pour un lien, ce que voit le visiteur |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | développeurs | fonctionnement, pages, langues, référencement, schéma de la base |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | hébergeur | installation serveur, `.env`, Nginx/Apache, cPanel, contrôle production |
| [docs/OPERATIONS_LOCALES.md](docs/OPERATIONS_LOCALES.md) | développeurs | serveurs locaux, synchronisation, contenu de démonstration, comptes |
| [docs/SECURITE_QUALITE.md](docs/SECURITE_QUALITE.md) | développeurs, recette | sécurité, accessibilité, critères de livraison |
| [AGENTS.md](AGENTS.md) | développeurs et agents | règles de travail sur le code |
| [CHANGELOG.md](CHANGELOG.md) | tous | historique des évolutions |

## Rappels

- Le serveur web n’expose que le dossier `public/`.
- Les secrets restent dans `.env` (ignoré par Git).
- Les modifications de code se font dans `template/`, jamais directement
  dans une instance.
