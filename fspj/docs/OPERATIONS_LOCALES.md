# Opérations locales

Procédures de travail sur la plateforme multi-dossiers en local : serveurs,
synchronisation du code, contenu de démonstration, cartes, comptes et
tests. Les mots de passe locaux ne figurent dans aucun document versionné :
ils sont dans `LOCAL_CREDENTIALS.md` à la racine (ignoré par Git).

## Instances et ports

| Instance | Adresse locale | Réglages clés du `.env` |
|---|---|---|
| fseg | http://localhost:8101 | `app.siteSlug=fseg`, `session.cookieName=ci_session_fseg` |
| fsi | http://localhost:8102 | `app.siteSlug=fsi`, `session.cookieName=ci_session_fsi` |
| superadmin | http://localhost:8103 | `app.centralAdminMode=true`, `session.cookieName=ci_session_central` |
| med | http://localhost:8104 | `app.siteSlug=med`, `session.cookieName=ci_session_med` |
| fabi | http://localhost:8105 | `app.siteSlug=fabi`, `session.cookieName=ci_session_fabi` |
| flsh | http://localhost:8106 | `app.siteSlug=flsh`, `session.cookieName=ci_session_flsh` |
| fs | http://localhost:8107 | `app.siteSlug=fs`, `session.cookieName=ci_session_fs` |
| fpse | http://localhost:8108 | `app.siteSlug=fpse`, `session.cookieName=ci_session_fpse` |
| fspj | http://localhost:8109 | `app.siteSlug=fspj`, `session.cookieName=ci_session_fspj` |

Utiliser `http://localhost:PORT` (pas `127.0.0.1`) pour que les cookies
correspondent à `app.baseURL`.

## Serveurs de développement

Le script vit dans chaque instance (`scripts/dev-serve.sh`) ; le port vient
de `app.baseURL` de chaque `.env`.

```bash
cd fseg
./scripts/dev-serve.sh start      # les instances présentes (ports 8101 à 8109)
./scripts/dev-serve.sh status     # chaque /healthz doit répondre 204
./scripts/dev-serve.sh stop
PLATFORM_INSTANCES='fseg fsi' ./scripts/dev-serve.sh start   # sous-ensemble
```

Toujours arrêter les serveurs à la fin d’une session de travail.

## Modifier le code

1. Modifier uniquement dans `template/`.
2. `cd template && ./scripts/sync-instances.sh` : recopie `app/`, `tests/`,
   `public/assets/`, `scripts/dev-serve.sh` et la documentation d’exploitation vers fseg, fsi,
   superadmin, med, fabi, flsh, fs, fpse et fspj.
3. Après une nouvelle migration : `cd superadmin && php spark migrate`
   (base partagée), puis `cd fseg && php spark testdb:migrate` (base de
   test).
4. Après une modification d’un service ou d’un filtre, vérifier que la
   classe chargée est celle du dossier courant :
   `php -r "require 'vendor/autoload.php'; echo (new ReflectionClass(App\Services\SiteResolverService::class))->getFileName();"`.

## Contenu de démonstration

`php spark site:seed-demo` remplit chaque faculté comme un site terminé :
formations, départements avec maquettes, personnel, actualités,
recherche, alumni, FAQ, partenaires, textes de toutes les pages, traductions
anglaises et images propres à la faculté. Les données viennent uniquement de
`FacultyDemoDataService` et `FacultyDemoInformationSeeder` ; les photos
viennent de `template/demo-media/faculties/{slug}/` (jamais recopié dans les
instances).

Lancer depuis `superadmin/`, pour que `app.uploadMirrors` recopie les
fichiers vers chaque faculté :

```bash
cd superadmin
php spark site:seed-demo --force              # toutes les facultés actives
php spark site:seed-demo --slug=fseg --force  # une seule faculté
```

Puis, à la racine de la plateforme, actualiser les cartes du campus
(`map_url` de la page Contact et adresses) :

```bash
php scripts/update-faculty-maps.php
```

| Faculté | Campus | Latitude, longitude |
|---|---|---|
| FSEG, FLSH | Mutanga | -3.376061, 29.383330 |
| FSI | Kiriri | -3.389031, 29.375322 |
| MED | CHUK Kamenge | -3.355472, 29.385669 |
| FABI | Gitega (approx.) | -3.408500, 29.934000 |

Le seeder `TemplateStarterSeeder` reste neutre (tests et nouveau site) : ne
jamais y mettre de données réelles. Les scripts
`scripts/seed-ub-faculty-content.php` et `seed-ub-faculty-data-fseg-fsi.php`
de la racine sont l’ancienne méthode, remplacée par `site:seed-demo`.

## Comptes locaux

| Commande | Où | Effet |
|---|---|---|
| `php spark admin:create-superadmin` | `superadmin/` uniquement | groupe Shield `superadmin` |
| `php spark admin:create-faculty-admin` | dossier facultaire | groupe `admin` sur cette faculté |
| `PLATFORM_ADMIN_PASSWORD='…' php scripts/ensure-faculty-admins.php` | racine | crée ou réinitialise les comptes locaux de test |

Un compte temporaire créé pour une vérification (captures d’écran,
recette) doit être supprimé ensuite.

## Tests

Voir [tests/README.md](../tests/README.md). En résumé, depuis une instance
(par exemple `fseg/`) :

```bash
php spark testdb:migrate
php vendor/bin/phpunit --configuration phpunit.dist.xml --no-logging tests/feature/FacultyInformationPagesTest.php
```

Lancer un fichier à la fois : la suite complète dépasse vingt minutes et
les processus parallèles se gênent sur la base de test.

## Médias

- Pack source : `template/demo-media/faculties/{slug}/` (non synchronisé).
- Fichiers servis : `public/uploads/sites/{slug}/` de chaque instance.
- Élément partagé : `public/assets/images/logo-placeholder.*` seulement.
