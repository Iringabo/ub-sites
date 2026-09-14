# Modèle de site facultaire — Université du Burundi

Ce dossier est le **modèle**. C’est le seul moyen de créer un nouveau site
facultaire : on le copie, on renseigne trois champs, on lance deux commandes.

Guide pas à pas, en français simple :
**[docs/CREER_UN_SITE.md](docs/CREER_UN_SITE.md)**.

Chaque faculté (et la superadministration) est un dossier séparé. Tous
partagent **une seule base de données** ; les contenus sont isolés par
`site_id`.

## Créer un site (résumé)

1. Copier ce dossier (`cp -r template/ fseg`).
2. Dans `.env` : URL publique, identifiants de la base partagée, `app.siteSlug`.
3. Une fois pour toute la plateforme : `php spark migrate --all`.
4. Dans le nouveau dossier : `php spark site:create …` puis le premier compte.

Le détail, y compris Composer et Nginx, est dans le guide ci-dessus.

## Lancer ce modèle en local

```bash
composer install
cp .env.example .env
php spark key:generate
# Renseigner database.default.* puis :
php spark migrate --all
php spark site:create --identifier exemple --slug exemple --name "Faculté de démonstration"
php spark admin:create-superadmin --email admin@example.test --username admin
php spark serve
```

- Public : `http://localhost:8080/`
- Administration : `http://localhost:8080/admin`
- Santé : `http://localhost:8080/healthz`

Ne copiez pas le `.env` d’une instance réelle. Partez de `.env.example`.

## Rôles

- **Éditeur** : contenus (accueil, actualités, formations, personnel,
  recherche). Pas de comptes ni de réglages d’identité.
- **Administrateur de faculté** : la même chose, plus messages, utilisateurs
  et coordonnées. Il peut créer un administrateur ou un éditeur *pour sa
  faculté seulement*.
- **Superadministrateur** : dossier `superadmin/`. Il choisit une faculté et
  en modifie le contenu ici, sur la base partagée. Il ne crée pas de site
  depuis l’écran d’administration.

## Tests

```bash
php spark test
```

`php spark test` supprime et recrée la base `database.tests.database`.
Lire [tests/README.md](tests/README.md) avant de lancer la suite.

## Documentation

- [Créer un site](docs/CREER_UN_SITE.md) — procédure pour un non-programmeur
- [Index](docs/README.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Déploiement multi-dossiers](docs/MULTI_FOLDER_DEPLOYMENT.md)
- [Administration](docs/GUIDE_ADMINISTRATEUR.md)
