# Architecture actuelle

Dernière actualisation : 10 septembre 2026.

Ce dossier est **une instance facultaire**, pas le modèle. On n’y crée pas
de nouveau site. Le modèle et la procédure sont dans `template/`.

```
<racine plateforme>/
├── template/      ← modèle (jamais exposé)
├── fseg/          ← ce type d’instance : public + /admin d’une faculté
├── fsi/           ← autre faculté
└── superadmin/    ← pilotage (édition in situ du site choisi)
```

- **Une seule base** partagée ; isolation par `site_id`.
- Migrations **une fois** pour toute la plateforme.
- Cookie de session propre à ce dossier (ex. `ci_session_fseg`).

## Local

| Instance | URL | Clé |
|---|---|---|
| cette faculté | voir `app.baseURL` dans `.env` | `app.siteSlug` |
| superadmin | http://localhost:**8103** | `app.centralAdminMode=true` |

```bash
./scripts/dev-serve.sh start    # démarre les instances listées
./scripts/dev-serve.sh status
./scripts/dev-serve.sh stop
```

## Résolution du site

1. `/admin/*` : site du dossier (le sélecteur n’existe pas pour le personnel
   facultaire) ;
2. hôte déclaré dans `hostnames` ;
3. `app.siteId` / `app.siteSlug` ;
4. site introuvable ou inactif → **404** (jamais de repli).

## Accès

| Qui | Peut faire ici |
|---|---|
| Éditeur | Contenu et communauté |
| Administrateur de faculté | Trois zones ; crée admin/éditeur **de cette faculté** ; identité |
| Superadministrateur | Se connecte de préférence sur `superadmin/` ; conserve l’accès à ce `/admin` |

Menu : trois zones (`AdminNavigationService`). Pas de liste des facultés.

## Isolation

`SiteScopedModel::forSite()`, médias sous `public/uploads/sites/{slug}/`.

## Front, langues, sécurité

Bootstrap / Icons / Inter auto-hébergés. Public FR/EN ; admin en français.
Détails : [SECURITY_REVIEW.md](SECURITY_REVIEW.md).

## Qualité

PHPUnit 10. `php spark test` recrée `database.tests.database`. Le code
applicatif est aligné sur `template/` par rsync de `app/`, `tests/`,
`public/assets/` — pas des README ni du `.env`.

```bash
composer validate && php spark routes && php spark migrate:status
php spark test
php spark app:production-check [--strict]
```

## Documents liés

- [../README.md](../README.md)
- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md)
- [05-BASE-DE-DONNEES.md](05-BASE-DE-DONNEES.md)
- [GUIDE_ADMINISTRATEUR.md](GUIDE_ADMINISTRATEUR.md)
