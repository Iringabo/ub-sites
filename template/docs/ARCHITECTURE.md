# Architecture actuelle

Dernière actualisation : 25 septembre 2026. Ce document décrit l’état
présent de la plateforme.

## Vue d’ensemble

Le dossier `template/` est le **modèle**. On le copie pour chaque instance.

```
<racine plateforme>/
├── template/      ← ce dépôt (jamais exposé au web)
├── fseg/          ← copie : app.siteSlug=fseg   → public + /admin FSEG
├── fsi/           ← copie : app.siteSlug=fsi    → public + /admin FSI
├── med/           ← copie : app.siteSlug=med
├── fabi/          ← copie : app.siteSlug=fabi
├── flsh/          ← copie : app.siteSlug=flsh
└── superadmin/    ← copie : app.centralAdminMode=true → pilotage
```

- **Une seule base MySQL/MariaDB** ; isolation par `site_id`.
- Migrations **une fois**, depuis n’importe quelle instance.
- Chaque instance : son `vendor/`, son `.env`, son cookie de session.
- Création d’une faculté : [CREER_UN_SITE.md](CREER_UN_SITE.md).
- Seed / cartes / admins locaux : [LOCAL_OPERATIONS.md](LOCAL_OPERATIONS.md).

## Développement local

| Instance | URL | `.env` clé |
|---|---|---|
| fseg | http://localhost:**8101** | `app.siteSlug=fseg`, `session.cookieName=ci_session_fseg` |
| fsi | http://localhost:**8102** | `app.siteSlug=fsi`, `session.cookieName=ci_session_fsi` |
| superadmin | http://localhost:**8103** | `app.centralAdminMode=true`, `session.cookieName=ci_session_central` |
| med | http://localhost:**8104** | `app.siteSlug=med`, `session.cookieName=ci_session_med` |
| fabi | http://localhost:**8105** | `app.siteSlug=fabi`, `session.cookieName=ci_session_fabi` |
| flsh | http://localhost:**8106** | `app.siteSlug=flsh`, `session.cookieName=ci_session_flsh` |

```bash
cd fseg && ./scripts/dev-serve.sh start
./scripts/dev-serve.sh status
./scripts/dev-serve.sh stop
```

Les ports viennent de `app.baseURL`. Par défaut le script lance les six
instances (`fseg fsi superadmin med fabi flsh`). Sous-ensemble :
`PLATFORM_INSTANCES='fseg fsi' ./scripts/dev-serve.sh start`.

## Résolution du site actif

`App\Services\SiteResolverService` :

1. `/admin/*` : site en session (superadmin), sinon site lié au dossier ;
2. hôte listé dans `hostnames` d’un site actif ;
3. `app.siteId` puis `app.siteSlug` ;
4. dossier facultaire HTTP : site introuvable ou inactif → **404** ;
5. superadmin / CLI : premier site actif, sinon entité neutre.

Les identifiants / slugs `template` et `demo` sont traités comme squelette
et exclus du sélecteur superadmin.

## Modèle d’accès

Groupes Shield : `superadmin`, `admin`, `editor`
(`app/Config/AuthGroups.php`).

| Qui | Où | Peut faire |
|---|---|---|
| Superadministrateur | `superadmin/` | facultés existantes, comptes de toutes les facultés, **édition in situ** du `site_id` choisi |
| Administrateur de faculté | `/admin` de son dossier | les quatre sections du menu ; crée un **administrateur** ou un **éditeur** de *sa* faculté |
| Éditeur | `/admin` de son dossier | Tableau de bord + Pages du site ; pas de messages, paramètres ni comptes |

`AdminAccessFilter` lie le personnel au site du dossier. Le sélecteur de
site est réservé au superadmin.

Le menu `/admin` (`AdminNavigationService::sections()`) a quatre sections :
**Au quotidien**, **Pages du site** (un menu déroulant par page publique,
construit depuis `PageTextCatalog` : « Sections de la page » puis
« Listes »), **Paramètres du site** et **Administration**. Sur l’instance
centrale, **Plateforme** vient en tête et les sections de la faculté
n’apparaissent qu’une fois la faculté choisie. Les modules « Pages
institutionnelles », « Blocs de page », « Points forts » et « Textes des
sections » sont retirés de l’UI (routes gardées). L’écran `/admin/sites`
n’offre plus la création.

## Isolation multi-sites

- Tables `sites` et `user_sites` ; `site_id` sur contenus, paramètres,
  messages, traductions.
- Lectures/écritures via `SiteScopedModel::forSite()`.
- Médias : `public/uploads/sites/{slug}/…`.

## Front-end auto-hébergé

Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Inter dans
`public/assets/vendor/`. CSP `'self'` ; exception
`frame-src https://www.google.com` pour la carte contact.

## Bilinguisme

Français source ; anglais public via `content_translations` (fallback FR).
Cookie de langue par instance (`site_locale_{slug}` ou `site_locale_central`).
Admin / auth : français. Les pages d’erreur publiques suivent la locale.

## Sécurité (synthèse)

Shield, CSRF, en-têtes, CSP, validation serveur, uploads (extension + MIME
+ `getimagesize`, noms aléatoires), rate-limit contact (e-mail 120 s, IP
20/h), verrous anti-auto-lockout et dernier superadmin. Détails :
[SECURITY_REVIEW.md](SECURITY_REVIEW.md).

`php spark app:production-check` contrôle notamment `settings.site_id`
lorsque la base connectée est bien celle configurée.

## Qualité

- PHPUnit 10, tests feature / database / unit dans `tests/`.
- `tests/bootstrap.php` **supprime et recrée** `database.tests.database`.
- Après une modification dans `template/app`, `template/tests` ou
  `template/public/assets`, recopier vers fseg, fsi, superadmin, med, fabi
  et flsh (voir [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md)).

## Commandes utiles

```bash
composer validate && php spark routes && php spark migrate:status
php spark test
php spark site:create --identifier med --slug med --name "Faculté de Médecine"
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
    --email admin-faculte@example.edu --username adminfac --password-env PLATFORM_ADMIN_PASSWORD
# Superadmin uniquement depuis le dossier superadmin/ :
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-superadmin \
    --email admin@example.edu --username admin --password-env PLATFORM_ADMIN_PASSWORD
php spark app:production-check [--strict]
```

## Documents liés

- [LOCAL_OPERATIONS.md](LOCAL_OPERATIONS.md)
- [CREER_UN_SITE.md](CREER_UN_SITE.md)
- [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md)
- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md)
- [05-BASE-DE-DONNEES.md](05-BASE-DE-DONNEES.md)
- [GUIDE_ADMINISTRATEUR.md](GUIDE_ADMINISTRATEUR.md)
