# Instructions pour agents — instance FABI

Ce dossier est le site de la **Faculté d'Agronomie et de Bioingénierie**, pas le
modèle. Ne documentez pas ici la création d’un nouveau site.

## Source de vérité

Lire le code de ce dossier. L’architecture générale :
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Mission de cette instance

- Servir le site public FABI et son `/admin`.
- `app.siteSlug = fabi`, `app.centralAdminMode = false`.
- Isolation des contenus par `site_id` sur la base partagée.
- L’administrateur de faculté crée uniquement des **éditeurs** de *cette*
  faculté et gère les coordonnées. Le superadministrateur se connecte sur le
  dossier superadmin. Doc plateforme à jour : `../template/docs/`
  (`sync-instances.sh` ne recopie pas la documentation).
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
