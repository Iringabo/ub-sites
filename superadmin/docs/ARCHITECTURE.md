# Architecture actuelle

Dernière actualisation : 10 septembre 2026.

Ce dossier est **l’instance de superadministration**, pas le modèle. On n’y
crée pas de nouveau site. Procédure : `template/docs/CREER_UN_SITE.md`.

```
<racine plateforme>/
├── template/      ← modèle
├── fseg/ / fsi/   ← sites publics + /admin facultaires
└── superadmin/    ← ce dossier : app.centralAdminMode=true
```

- **Une seule base** ; l’édition porte sur le `site_id` **choisi**.
- Cookie : `ci_session_central`.
- `CentralAdminOnlyFilter` : le public redirige vers `/admin`.

## Local

http://localhost:**8103**/admin — groupe Shield `superadmin` uniquement.

```bash
./scripts/dev-serve.sh start
```

## Comportement

1. Tableau de bord plateforme : facultés réelles (pas `template` / `demo`).
2. Sélecteur ou **Gérer le contenu** → `active_admin_site_id`.
3. Les trois zones éditent cette faculté dans **cette** application.
4. **Voir le site** ouvre l’hôte public de la faculté (nouvel onglet).
5. **Facultés** : modifier une fiche existante. Création UI désactivée.
6. **Comptes** : premier admin d’une faculté ; ensuite il gère ses éditeurs
   dans le dossier de sa faculté.

## Isolation

Même `SiteScopedModel::forSite()` que les facultés, avec le site de session.

## Qualité

PHPUnit 10. `php spark test` recrée `database.tests.database`. Aligner le
code sur `template/` par rsync de `app/`, `tests/`, `public/assets/`.

```bash
php spark app:production-check [--strict]
php spark test
```

## Documents liés

- [../README.md](../README.md)
- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md)
- [GUIDE_ADMINISTRATEUR.md](GUIDE_ADMINISTRATEUR.md)
