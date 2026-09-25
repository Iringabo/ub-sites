# Faculté de Médecine (MED)

Site public et administration de **cette** faculté. La base de données est
partagée avec les autres facultés ; seuls les contenus MED s’affichent ici.

Ce dossier n’est pas un modèle : on ne s’en sert pas pour ouvrir une autre
faculté. Pour créer un site : dossier `template/`
(`template/docs/CREER_UN_SITE.md`).

## Lancer ce site

```bash
composer install
cp .env.example .env
php spark key:generate
```

Dans `.env`, vérifiez surtout :

- `app.baseURL` — adresse de ce site (en local : `http://localhost:8104/`)
- `app.siteSlug = med`
- `app.centralAdminMode = false`
- `session.cookieName = ci_session_med`
- `database.default.*` — la base partagée

Puis :

```bash
./scripts/dev-serve.sh
```

- Public : `http://localhost:8104/`
- Administration : `http://localhost:8104/admin`
- Santé : `http://localhost:8104/healthz`

Premier administrateur de faculté (depuis ce dossier) :

```bash
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
  --email med-admin@ub.local --username medadmin \
  --password-env PLATFORM_ADMIN_PASSWORD
```

Les identifiants locaux de la plateforme sont dans `LOCAL_CREDENTIALS.md`
à la racine (fichier ignoré par Git).

## Qui fait quoi dans `/admin`

- **Éditeur** : accueil, actualités, formations, personnel, recherche.
- **Administrateur de la faculté** : la même chose, plus messages, comptes
  (uniquement des **éditeurs** de *cette* faculté) et coordonnées / identité.

## Tests

```bash
php spark test
```

La commande recrée la base `database.tests.database`. Lire
[tests/README.md](tests/README.md).

## Documentation

- [Guide administrateur](docs/GUIDE_ADMINISTRATEUR.md)
- [Guide du site public](docs/GUIDE_UTILISATEUR_CLIENT.md)
- [Index](docs/README.md)
- Opérations plateforme : [template/docs/LOCAL_OPERATIONS.md](../template/docs/LOCAL_OPERATIONS.md)
