# Faculté des Sciences Économiques et de Gestion (FSEG)

Site public et administration de **cette** faculté. La base de données est
partagée avec les autres facultés ; seuls les contenus FSEG s’affichent ici.

Ce dossier n’est pas un modèle : on ne s’en sert pas pour ouvrir une autre
faculté.

## Lancer ce site

```bash
composer install
cp .env.example .env
php spark key:generate
```

Dans `.env`, vérifiez surtout :

- `app.baseURL` — adresse de ce site (en local : `http://localhost:8101/`)
- `app.siteSlug = fseg`
- `app.centralAdminMode = false`
- `session.cookieName = ci_session_fseg`
- `database.default.*` — la base partagée

Puis :

```bash
./scripts/dev-serve.sh
```

- Public : `http://localhost:8101/`
- Administration : `http://localhost:8101/admin`
- Santé : `http://localhost:8101/healthz`

## Qui fait quoi dans `/admin`

- **Éditeur** : accueil, actualités, formations, pages, personnel, recherche.
- **Administrateur de la faculté** : la même chose, plus messages, comptes
  (admin ou éditeur de *cette* faculté) et coordonnées / identité.

Le tableau de bord propose une liste de mise en route tant que les textes
d’exemple n’ont pas été remplacés.

## Tests

```bash
php spark test
```

La commande recrée la base `database.tests.database`. Lire
[tests/README.md](tests/README.md).

Identifiants locaux : `LOCAL_CREDENTIALS.md` à la racine (gitignored).

## Documentation

- [Guide administrateur](docs/GUIDE_ADMINISTRATEUR.md)
- [Guide du site public](docs/GUIDE_UTILISATEUR_CLIENT.md)
- [Index](docs/README.md)
