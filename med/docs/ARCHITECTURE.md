# Architecture actuelle

Dernière actualisation : 10 septembre 2026. Ce document décrit l’état
présent du dépôt modèle.

## Vue d’ensemble

Ce dossier est le **modèle**. On le copie pour chaque instance.

```
<racine plateforme>/
├── template/      ← ce dépôt (jamais exposé au web)
├── fseg/          ← copie : app.siteSlug=fseg   → public + /admin FSEG
├── fsi/           ← copie : app.siteSlug=fsi    → public + /admin FSI
└── superadmin/    ← copie : app.centralAdminMode=true → pilotage
```

- **Une seule base MySQL/MariaDB** ; isolation par `site_id`.
- Migrations **une fois**, depuis n’importe quelle instance.
- Chaque instance : son `vendor/`, son `.env`, son cookie de session.
- Création d’une faculté : [CREER_UN_SITE.md](CREER_UN_SITE.md).

## Développement local

| Instance | URL | `.env` clé |
|---|---|---|
| fseg | http://localhost:**8101** | `app.siteSlug=fseg`, `session.cookieName=ci_session_fseg` |
| fsi | http://localhost:**8102** | `app.siteSlug=fsi`, `session.cookieName=ci_session_fsi` |
| superadmin | http://localhost:**8103** | `app.centralAdminMode=true`, `session.cookieName=ci_session_central` |

```bash
cd fseg && ./scripts/dev-serve.sh start
./scripts/dev-serve.sh status
./scripts/dev-serve.sh stop
```

Les ports viennent de `app.baseURL` (`PLATFORM_INSTANCES` peut surcharger
la liste).

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
| Superadministrateur | `superadmin/` | facultés existantes, comptes, **édition in situ** du `site_id` choisi |
| Administrateur de faculté | `/admin` de son dossier | trois zones ; crée admin/éditeur **de sa faculté** ; identité |
| Éditeur | `/admin` de son dossier | contenu ; pas d’utilisateurs ni d’identité |

`AdminAccessFilter` lie le personnel au site du dossier. Le sélecteur de
site est réservé au superadmin.

Le menu `/admin` a trois zones métier (`AdminNavigationService`) : contenu
et communication ; communauté et recherche ; administration. Sur
l’instance centrale s’ajoute le groupe Plateforme. L’écran `/admin/sites`
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
  `template/public/assets`, recopier vers fseg, fsi et superadmin
  (voir [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md)).

## Commandes utiles

```bash
composer validate && php spark routes && php spark migrate:status
php spark test
php spark site:create --identifier droit --slug droit --name "Faculté de Droit"
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-superadmin \
    --email admin@example.edu --username admin
php spark app:production-check [--strict]
```

## Documents liés

- [CREER_UN_SITE.md](CREER_UN_SITE.md)
- [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md)
- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md)
- [05-BASE-DE-DONNEES.md](05-BASE-DE-DONNEES.md)
- [GUIDE_ADMINISTRATEUR.md](GUIDE_ADMINISTRATEUR.md)
