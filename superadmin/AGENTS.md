# Instructions pour agents — superadministration

Ce dossier est l’instance de **pilotage** des facultés déjà créées. Ce n’est
pas le modèle. On n’y crée pas de nouveau site.

## Source de vérité

Lire le code de ce dossier. L’architecture générale :
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Mission de cette instance

- `app.centralAdminMode = true`. Réservé au groupe `superadmin`.
- Tableau de bord plateforme : facultés existantes (sans le squelette
  interne), comptes de toutes les facultés.
- Le sélecteur de faculté enregistre `active_admin_site_id`. Les écrans
  éditoriaux écrivent ce `site_id` dans la base partagée.
- **Voir le site** ouvre l’hôte public de la faculté choisie.

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
