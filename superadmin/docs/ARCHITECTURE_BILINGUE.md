# Architecture Bilingue

Dernière actualisation documentaire : 11 août 2026.

Le français est la langue source et la locale par défaut. Le public peut afficher le français ou l'anglais. L'administration et l'authentification restent en français.

## Configuration

- `app.defaultLocale = fr`
- `supportedLocales = ['fr', 'en']`
- `negotiateLocale = false`
- `LocaleFilter` est global.
- `/login`, `/logout` et `/admin` sont forcés en français.

## Détection Côté Public

Priorité réelle :

1. paramètre `?lang=fr` ou `?lang=en`;
2. cookie `site_locale_{slug}` (ou `site_locale_central` sur l’instance superadmin) ;
3. headers pays `CF-IPCountry`, `X-Vercel-IP-Country`, `CloudFront-Viewer-Country` **uniquement** si `app.proxyIPs` est renseigné ;
4. header `Accept-Language`;
5. `sites.default_locale`;
6. fallback `fr`.

La route `/language/{locale}` pose le cookie d’instance et redirige vers une URL sûre. Les URLs externes ne doivent pas être utilisées comme cible de retour.

## Stockage Des Traductions

Les traductions éditoriales sont stockées dans `content_translations` :

- `site_id`
- `resource_type`
- `resource_id`
- `locale`
- `field`
- `value`

L'anglais utilise `locale=en`. Si une traduction manque, le champ français original est affiché.

## Champs Traduisibles

Les modules administrables exposent une section "Version anglaise" lorsque des champs traduisibles existent :

- accueil;
- carrousel;
- statistiques;
- programmes;
- personnel;
- laboratoires;
- publications;
- projets de recherche;
- historique;
- alumni;
- témoignages;
- paramètres;
- actualités et événements.

Les tables / modules `home_highlights` (atouts) et `pages` / blocs restent
traduisibles en base pour données legacy, mais n’ont plus d’écran d’édition
dans le menu admin.

La section "Mot du doyen" de la page Faculté est administrée par un écran dédié. Le français reste stocké dans `pages.content.dean`; les champs anglais sont stockés dans `content_translations` comme traduction du champ `content` de la ressource `pages`.

Le module `sites` n'a pas de champs traduisibles dans l'interface actuelle.

## Pages Concernées

Les pages publiques utilisent les traductions :

- accueil;
- faculté;
- formations et détail formation;
- recherche;
- corps enseignant et détail membre;
- actualités/événements et détail post;
- alumni;
- contact pour les libellés et messages.

Les routes restent en français afin de conserver une structure stable.

## Administration Et Authentification

Les écrans admin, login/logout, notifications back-office, validations et messages d'erreur visibles par l'administrateur restent en français. Les traductions anglaises sont saisies depuis le back-office, mais l'interface de saisie reste française.

## Héritage Technique

Une migration a ajouté des colonnes `_en` à `posts` pendant la transition. Le système courant centralise les traductions dans `content_translations` et migre les valeurs utiles depuis ces colonnes lorsqu'elles existent.
