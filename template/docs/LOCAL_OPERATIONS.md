# Opérations locales (seed, cartes, admins)

Procédures utilisées sur cette plateforme multi-dossiers et **absentes** des
guides d’installation génériques. Exécuter depuis la **racine** du dépôt
(`fseg-main-multifolder/`), sauf indication contraire.

Les mots de passe réels ne sont **pas** dans ce document : voir
`LOCAL_CREDENTIALS.md` (gitignored) à la racine.

## Contenu facultaire (UB)

Remplace le contenu neutre de démarrage par des textes / programmes / contacts
alignés sur les sources UB pour **fseg, fsi, med, fabi, flsh** :

```bash
php scripts/seed-ub-faculty-content.php
```

- Lit la connexion MySQL depuis `fseg/.env` (`database.default.*`).
- Payloads FSEG/FSI : [`scripts/seed-ub-faculty-data-fseg-fsi.php`](../../scripts/seed-ub-faculty-data-fseg-fsi.php).
- Images locales référencées : `public/assets/images/faculties/{slug}/hero-1.jpg` … `banner.jpg`
  (présentes sous `template/` puis synchronisées vers les instances).

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

Sous chaque instance : `public/assets/images/faculties/{fseg,fsi,med,fabi,flsh}/`.
Source de vérité des binaires : `template/public/assets/images/faculties/`.
Après ajout : `cd template && ./scripts/sync-instances.sh`.

## Ports et `dev-serve`

Le script lance par défaut les six instances présentes
(`fseg fsi superadmin med fabi flsh`). Sous-ensemble possible via
`PLATFORM_INSTANCES`.

```bash
./scripts/dev-serve.sh start
# ou : PLATFORM_INSTANCES='fseg fsi' ./scripts/dev-serve.sh start
```

Les ports viennent de `app.baseURL` dans chaque `.env` (8101–8106).

## Documents liés

- [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md)
- [CREER_UN_SITE.md](CREER_UN_SITE.md)
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [README racine](../../README.md)
