# Commencer ici

Ce dossier est le **modèle** de la plateforme : on le copie pour créer un
site facultaire (public + `/admin`) ou l’instance superadministration.

## Lecture rapide

1. [docs/CREER_UN_SITE.md](docs/CREER_UN_SITE.md) — copier le dossier et
   ouvrir un site, sans être programmeur.
2. [README.md](README.md) — vue d’ensemble.
3. [docs/README.md](docs/README.md) — choisir le bon guide.

## Lancement local minimal (faculté de démo)

```bash
composer install
cp .env.example .env
php spark key:generate
# Renseigner database.default.* et app.siteSlug=exemple, puis :
php spark migrate --all
php spark site:create --identifier exemple --slug exemple --name "Faculté de démonstration"
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
  --email admin@example.test --username admin --password-env PLATFORM_ADMIN_PASSWORD
php spark serve
```

Le site public est sur `http://localhost:8080/`. L’administration est sur
`/admin`.

`admin:create-superadmin` ne fonctionne que dans un dossier avec
`app.centralAdminMode=true` (typiquement `superadmin/`). Voir
[docs/CREER_UN_SITE.md](docs/CREER_UN_SITE.md).

## Rappels

- Ne jamais copier un `.env` réel d’une instance vers une autre.
- Les tests recréent la base `database.tests.database` : lire
  [tests/README.md](tests/README.md).
- Le serveur web n’expose que le dossier `public/`.
