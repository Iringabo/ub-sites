# Déploiement multi-dossiers (un dossier par site)

> **Pour ouvrir une faculté** : suivre [CREER_UN_SITE.md](CREER_UN_SITE.md)
> (copie du dossier, réglages `.env` essentiels, migrations + CLI). Ce document
> explique le fonctionnement technique.

Toutes les instances partagent le même code applicatif, les mêmes
migrations et **une seule base de données**. Seuls l’organisation des
dossiers et le `.env` de chaque dossier changent.

En local : FSEG = 8101, FSI = 8102, superadmin = 8103, MED = 8104,
FABI = 8105, FLSH = 8106 via `scripts/dev-serve.sh` (voir
[LOCAL_OPERATIONS.md](LOCAL_OPERATIONS.md) pour `PLATFORM_INSTANCES`).

## Vue d’ensemble

```
/var/www/
├── template/       → le modèle (jamais exposé au web)
├── superadmin/     → copie, app.centralAdminMode = true
│                      Superadmin : choisit une faculté et en édite le
│                      contenu ici (même site_id). Liste / comptes.
├── fseg/           → copie, app.siteSlug = fseg
├── fsi/            → copie, app.siteSlug = fsi
├── med/            → copie, app.siteSlug = med
├── fabi/           → copie, app.siteSlug = fabi
└── flsh/           → copie, app.siteSlug = flsh
```

Ce qui distingue un dossier d’un autre, ce sont quelques lignes du `.env` :

| Variable | Dans `superadmin/` | Dans un dossier facultaire |
|---|---|---|
| `app.centralAdminMode` | `true` | `false` |
| `app.siteSlug` | sans effet | `fseg`, `fsi`, `med`, `fabi`, `flsh`, … |
| `session.cookieName` | `ci_session_central` | `ci_session_fseg`, … |
| `session.rememberCookieName` | `remember_central` | `remember_fseg`, … |
| `app.uploadMirrors` | carte `slug:/chemin` | laisser vide |
| `app.previewBase.{slug}` | URLs d’aperçu local | inutile |
| `app.proxyIPs` | IPs du reverse proxy, ou vide | idem |
| `database.default.*` | **identique partout** | **identique partout** |

L’isolation des contenus est la colonne `site_id`, pas le dossier. Voir
[ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md).

## Comment ça marche

- `SiteResolverService` choisit le site : hôte connu → `app.siteId` →
  `app.siteSlug`. Un dossier facultaire dont le site est introuvable
  répond 404 — jamais de repli vers une autre faculté.
- En `/admin`, un superadmin peut forcer le site via
  `active_admin_site_id` (sélecteur). Les sites `template` et `demo` sont
  exclus.
- `isCentralAdminInstance()` lit `app.centralAdminMode`.
- `CentralAdminOnlyFilter` : si le mode central est vrai, toute URL hors
  `/admin/*` et hors authentification redirige vers `/admin`.
- Cookie de session **par instance** pour que localhost:8101 et
  localhost:8103 ne s’écrasent pas.

Les permissions Shield (superadmin / admin / éditeur) sont les mêmes dans
tous les dossiers. L’édition superadmin du contenu se fait **dans**
`superadmin/`, sur le `site_id` choisi.

## Créer une faculté

Procédure pour un non-programmeur : [CREER_UN_SITE.md](CREER_UN_SITE.md).

1. Copier `template/` (ou `scripts/new-faculty-instance.sh` depuis ce
   dossier modèle).
2. `.env` : URL, base partagée, `app.siteSlug`, cookies de session / remember,
   et `app.proxyIPs` si un reverse proxy est utilisé.
3. `php spark migrate --all` **une fois** pour toute la plateforme.
4. Dans le nouveau dossier : `php spark site:create …`.
5. Premier administrateur de la faculté, **depuis ce dossier** :

```bash
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
  --email admin-faculte@example.edu --username adminfac \
  --password-env PLATFORM_ADMIN_PASSWORD
```

   Ou bien : **Comptes** dans `superadmin/`. Ensuite cet administrateur
   crée ses éditeurs depuis le `/admin` de sa faculté.

On ne crée plus de faculté depuis l’écran `/admin/sites`.

## Dossier superadministration (une fois)

```bash
./scripts/new-admin-instance.sh ../superadmin
cd ../superadmin && php spark admin:create-superadmin --email vous@example.edu --username vous
```

`admin:create-superadmin` ne fonctionne que si `app.centralAdminMode=true`
(dossier superadmin). Depuis une faculté, utilisez
`admin:create-faculty-admin`. Un superadmin existant peut aussi CRUD tous
les admins (y compris d’autres superadmins) via l’UI Comptes.
Placez-le hors du document root des facultés. En local :
`http://localhost:8103`.

## Maintenir les copies

Un correctif de code doit être répété. Source : `template/`.

```bash
cd template && ./scripts/sync-instances.sh
```

Le script recopie uniquement `app/`, `tests/` et `public/assets/` vers
`fseg/`, `fsi/`, `superadmin/`, `med/`, `fabi/` et `flsh/` (s’ils existent). Équivalent manuel :

```bash
rsync -a template/app/ fseg/app/
rsync -a template/app/ fsi/app/
rsync -a template/app/ superadmin/app/
rsync -a template/app/ med/app/
rsync -a template/app/ fabi/app/
rsync -a template/app/ flsh/app/
# idem tests/ et public/assets/ — ou utiliser sync-instances.sh
```

Ne pas recopier `.env`, `writable/`, `docs/` ni les README (ils divergent
par instance). Relancer `php spark migrate --all` **une fois** sur la
base partagée.

Chaque instance a son propre `vendor/` : `--link-vendor` est refusé par
les scripts de création (l’autoload `App\\` pointerait vers le modèle).

## Documents liés

- [LOCAL_OPERATIONS.md](LOCAL_OPERATIONS.md) — seed UB, cartes, admins locaux
- [CREER_UN_SITE.md](CREER_UN_SITE.md)
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md)
- [GUIDE_DEPLOIEMENT_CPANEL.md](GUIDE_DEPLOIEMENT_CPANEL.md)
