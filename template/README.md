# Modèle de site facultaire — Université du Burundi

Ce dossier est le **modèle**. C’est le seul moyen de créer un nouveau site
facultaire : on le copie, on renseigne le `.env`, on lance migrations et
commandes CLI.

Guide pas à pas, en français simple :
**[docs/CREER_UN_SITE.md](docs/CREER_UN_SITE.md)**.

Chaque faculté (et la superadministration) est un dossier séparé. Tous
partagent **une seule base de données** ; les contenus sont isolés par
`site_id`.

## Créer un site (résumé)

1. Copier ce dossier (`cp -r template/ fseg`).
2. Dans `.env` : URL publique, identifiants de la base partagée, `app.siteSlug`,
   cookies de session, éventuellement `app.publicHostPattern`.
3. Une fois pour toute la plateforme : `php spark migrate --all`.
4. Dans le nouveau dossier : `php spark site:create …` puis
   `php spark admin:create-faculty-admin …`.

Le détail, y compris Composer et Nginx, est dans le guide ci-dessus.

## Lancer ce modèle en local

Le modèle seul n’est pas une instance « superadmin ». Pour un essai isolé
du modèle (faculté de démo) :

```bash
composer install
cp .env.example .env
php spark key:generate
# Renseigner database.default.* et app.siteSlug, puis :
php spark migrate --all
php spark site:create --identifier exemple --slug exemple --name "Faculté de démonstration"
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
  --email admin@example.test --username admin --password-env PLATFORM_ADMIN_PASSWORD
php spark serve
```

Pour créer un **superadministrateur**, utilisez le dossier `superadmin/`
(`app.centralAdminMode=true`) et `php spark admin:create-superadmin`
(voir [docs/CREER_UN_SITE.md](docs/CREER_UN_SITE.md)).

- Public : `http://localhost:8080/`
- Administration : `http://localhost:8080/admin`
- Santé : `http://localhost:8080/healthz`

Ne copiez pas le `.env` d’une instance réelle. Partez de `.env.example`.

## Rôles

- **Éditeur** : contenus (accueil, actualités, formations, personnel,
  recherche). Pas de comptes ni de réglages d’identité.
- **Administrateur de faculté** : la même chose, plus messages, utilisateurs
  et coordonnées. Il peut créer uniquement un **éditeur** *pour sa faculté
  seulement*.
- **Superadministrateur** : dossier `superadmin/`. Il choisit une faculté et
  en modifie le contenu ici, sur la base partagée. Seul rôle qui crée des
  administrateurs de faculté. Il ne crée pas de site depuis l’écran
  d’administration.

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
