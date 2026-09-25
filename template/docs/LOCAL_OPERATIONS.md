# Opérations locales (seed, cartes, admins)

Procédures utilisées sur cette plateforme multi-dossiers et **absentes** des
guides d’installation génériques. Exécuter depuis la **racine** du dépôt
(`fseg-main-multifolder/`), sauf indication contraire.

Les mots de passe réels ne sont **pas** dans ce document : voir
`LOCAL_CREDENTIALS.md` (gitignored) à la racine.

## Contenu démo « site terminé » (recommandé)

Remplit chaque faculté avec un volume de contenu public crédible (formations,
personnel, actualités, labs, etc.) et des **images propres au site** sous
`public/uploads/sites/{slug}/` uniquement — sans cloner les photos des autres
facultés dans chaque dossier.

Pack source (non synchronisé) : [`template/demo-media/`](../demo-media/README.md).

Depuis **superadmin** (pour que `app.uploadMirrors` recopie vers chaque faculté) :

```bash
cd superadmin
php spark site:seed-demo --force
# ou une seule faculté :
php spark site:seed-demo --slug=fseg --force
```

Après sync du code : `cd template && ./scripts/sync-instances.sh` (ne copie plus
`assets/images/faculties/` ; le pack reste dans `template/demo-media/`).

## Contenu facultaire UB (script legacy)

Remplace le contenu neutre de démarrage par des textes / programmes / contacts
alignés sur les sources UB pour **fseg, fsi, med, fabi, flsh** :

```bash
php scripts/seed-ub-faculty-content.php
```

- Lit la connexion MySQL depuis `fseg/.env` (`database.default.*`).
- Payloads FSEG/FSI : [`scripts/seed-ub-faculty-data-fseg-fsi.php`](../../scripts/seed-ub-faculty-data-fseg-fsi.php).
- Les photos facultaires pour le seed démo Spark sont dans
  `template/demo-media/faculties/{slug}/` (plus dans `public/assets/images/faculties/`).

Le seeder CI `TemplateStarterSeeder` reste **neutre** (tests / nouveau site) :
ne pas y coller le contenu UB réel.

## Cartes contact (campus)

Met à jour `map_url` des pages contact + adresses settings / sites :

```bash
php scripts/update-faculty-maps.php
```

Coordonnées actuelles (approx.) :

| Faculté | Campus | Lat, Lon |
|---|---|---|
| FSEG, FLSH | Mutanga | -3.376061, 29.383330 |
| FSI | Kiriri (Ch. P. L. Rwagasore) | -3.389031, 29.375322 |
| MED | CHUK Kamenge | -3.355472, 29.385669 |
| FABI | Zege / Gitega (approx. RN15) | -3.408500, 29.934000 |

L’iframe Google Maps est autorisée par la CSP (`frame-src https://www.google.com`).

## Administrateurs locaux

Règles CLI :

| Commande | Où | Effet |
|---|---|---|
| `admin:create-superadmin` | `superadmin/` uniquement | Groupe Shield `superadmin` |
| `admin:create-faculty-admin` | dossier facultaire (`siteSlug` non vide) | `admin` + `user_sites.site_admin` |

Helper racine (crée ou réinitialise les cinq admins faculté + `sup@test.com`) :

```bash
PLATFORM_ADMIN_PASSWORD='…' php scripts/ensure-faculty-admins.php
```

## Images offline

Pack source (non synchronisé) : `template/demo-media/faculties/{slug}/`.
Images runtime : `public/uploads/sites/{slug}/` sur chaque instance (et miroir
depuis superadmin via `app.uploadMirrors`).
Chrome partagé uniquement : `public/assets/images/logo-placeholder.*`.

## Accueil (héros & sections)

- Chaque slide : image + badge + titre + texte + boutons (cible via
  liste déroulante : aucun, formations, contact, actualités, etc.).
- **Sections & ordre** (`admin/home-sections`) : cocher et glisser-déposer.
- Les modules « Pages institutionnelles », « Blocs de page » et
  « Points forts » ne sont plus exposés dans le menu admin.

## Serveurs locaux

Le script `dev-serve.sh` est dans **chaque dossier d’instance** (ex. `fseg/`),
pas à la racine de la plateforme. Depuis une instance, il lance par défaut
les six sites présents (`fseg fsi superadmin med fabi flsh`). Sous-ensemble
possible via `PLATFORM_INSTANCES`.

```bash
cd fseg   # ou fsi, superadmin, med, fabi, flsh
./scripts/dev-serve.sh start
# ou : PLATFORM_INSTANCES='fseg fsi' ./scripts/dev-serve.sh start
```

Les ports viennent de `app.baseURL` dans chaque `.env` (8101–8106).
Préférer `http://localhost:PORT` (pas `127.0.0.1`) pour les cookies.

## Documents liés

- [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md)
- [CREER_UN_SITE.md](CREER_UN_SITE.md)
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [README racine](../../README.md)
