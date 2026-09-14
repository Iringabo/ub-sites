# Instructions pour agents — instance FLSH

Ce dossier est le site de la **Faculté des Lettres et des Sciences Humaines**, pas le
modèle. Ne documentez pas ici la création d’un nouveau site.

## Source de vérité

Lire le code de ce dossier. L’architecture générale :
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Mission de cette instance

- Servir le site public FLSH et son `/admin`.
- `app.siteSlug = flsh`, `app.centralAdminMode = false`.
- Isolation des contenus par `site_id` sur la base partagée.
- L’administrateur de faculté gère les comptes admin/éditeur de *cette*
  faculté et les coordonnées. Le superadministrateur se connecte sur le
  dossier superadmin.
- Création d’admin local : `php spark admin:create-faculty-admin` (pas
  `admin:create-superadmin`).

## Stack

PHP `^8.2`, CodeIgniter 4.7, Shield 1.3, MySQL/MariaDB, Bootstrap 5 auto-hébergé.

Chaque instance a son propre `vendor/`.

## Commandes utiles

```bash
php spark routes
php spark migrate:status
php spark app:production-check
php spark test
```

Avant `php spark test`, vérifier que `database.tests.database` est une base
dédiée : le bootstrap la supprime et la recrée.
