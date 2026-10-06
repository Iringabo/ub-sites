# Architecture

Ce document décrit l’état actuel de la plateforme : organisation des
dossiers, résolution du site, accès, langues, référencement, sécurité et
schéma de la base (en annexe). En cas de doute, le code fait foi
(`app/Config/Routes.php`, migrations, services).

## 1. Vue d’ensemble

Le dossier `template/` est le **modèle**. Chaque site est une copie de ce
dossier, configurée par son propre `.env`.

```
<racine plateforme>/
├── template/      ← modèle, jamais exposé au web
├── fseg/          ← app.siteSlug=fseg  → site public + /admin FSEG
├── fsi/           ← app.siteSlug=fsi
├── med/           ← app.siteSlug=med
├── fabi/          ← app.siteSlug=fabi
├── flsh/          ← app.siteSlug=flsh
└── superadmin/    ← app.centralAdminMode=true → pilotage de toutes les facultés
```

- **Une seule base MySQL/MariaDB** (InnoDB, `utf8mb4`) pour toutes les
  instances ; les contenus sont isolés par la colonne `site_id`.
- Les migrations se lancent **une fois** pour toute la plateforme, depuis
  n’importe quelle instance (`php spark migrate --all`).
- Chaque instance a son `vendor/`, son `.env`, ses cookies de session et
  son dossier `public/` (seul dossier exposé au web).
- Le code se modifie uniquement dans `template/`, puis
  `template/scripts/sync-instances.sh` recopie `app/`, `tests/`,
  `public/assets/` et la documentation d’exploitation vers les instances.

### Pile technique

- PHP 8.2 ou plus, CodeIgniter 4.7, CodeIgniter Shield 1.3, PHPUnit 10.
- Bootstrap 5.3, Bootstrap Icons et la police Inter **auto-hébergés** dans
  `public/assets/vendor/` (aucun CDN).
- Routes explicites (`app/Config/Routes.php`), auto-routage désactivé.
- Contrôleurs légers ; lecture et assemblage dans les modèles et services
  (`app/Services`, enregistrés dans `app/Config/Services.php`).

## 2. Résolution du site actif

`App\Services\SiteResolverService` choisit le site servi.

**Site public :**

1. hôte de la requête (`Host`, puis `X-Forwarded-Host` **seulement** si
   `app.proxyIPs` est renseigné) comparé aux `hostnames` des sites actifs ;
2. si `app.requireKnownHostname=true` et qu’aucun site ne correspond : 404 ;
3. `app.siteId`, puis `app.siteSlug` (obligatoire sur un dossier
   facultaire) ;
4. site introuvable, inactif ou base indisponible sur un dossier
   facultaire : **404**, jamais de repli vers une autre faculté.

**Administration :**

1. sur l’instance centrale, faculté choisie dans le sélecteur
   (`active_admin_site_id` en session) si l’utilisateur y a accès ;
2. sinon le site lié au dossier.

Les identifiants `template` et `demo` sont des squelettes : ils sont exclus
du sélecteur superadmin et des copies de médias.

Réglages `.env` liés : `app.allowedHostnames` (hôtes acceptés pour
`site_url()`), `app.centralAdminHosts` (hôtes réservés à la
superadministration), `app.proxyIPs` (filtre partagé
`App\Support\TrustedProxies`).

## 3. Accès et administration

Groupes Shield (`app/Config/AuthGroups.php`) : `superadmin`, `admin`,
`editor`.

| Qui | Où | Peut faire |
|---|---|---|
| Superadministrateur | dossier `superadmin/` | toutes les facultés existantes : contenu de la faculté choisie, comptes, fiche du site (nom, statut, couleurs) |
| Administrateur de faculté | `/admin` de son dossier | contenus, messages, paramètres, comptes de **sa** faculté ; crée des administrateurs et des éditeurs de sa faculté |
| Éditeur | `/admin` de son dossier | tableau de bord et pages du site ; ni messages, ni paramètres, ni comptes |

- `AdminAccessFilter` lie le personnel au site de son dossier ;
  `CentralAdminOnlyFilter` limite l’instance centrale à `/admin` et à
  l’authentification, pour le seul groupe `superadmin`.
- Un superadministrateur se crée **uniquement** en ligne de commande, dans
  `superadmin/` (`php spark admin:create-superadmin`) ; ses lignes sont en
  lecture seule dans l’écran Comptes. La suppression d’un compte est
  définitive.
- Aucune faculté ne se crée depuis l’interface ; c’est une opération des
  mainteneurs du modèle. Les domaines d’un site ne s’éditent pas depuis
  l’interface.

### Menu `/admin`

`AdminNavigationService::sections()` construit quatre sections :

1. **Au quotidien** : tableau de bord, messages reçus.
2. **Pages du site** : un menu par page publique, construit depuis
   `PageTextCatalog` : « Sections de la page » (petits formulaires
   `admin/textes/{page}/{section}`, `TextSegmentController`) puis
   « Listes » (ressources : programmes, personnel, articles…,
   `ResourceController`).
3. **Paramètres du site** : `admin/settings/{identity|contact|social|footer|seo}`
   (`SettingsController::PAGES`).
4. **Administration** : comptes et rôles.

Sur l’instance centrale, **Plateforme** vient en tête et les sections de la
faculté n’apparaissent qu’une fois une faculté choisie. Ctrl+K cherche dans
tout le menu. Les modules « pages », « blocs », « points forts » et
`home-content` sont retirés de l’interface (routes gardées et protégées).

### Liens internes

Les champs « lien » des sections de page et des diapositives du carrousel
utilisent un sélecteur de page (`admin/partials/link_picker.php`, script
`public/assets/js/admin.js`) alimenté par `SitePageCatalog`
(`service('sitePageCatalog')`) : pages principales, puis départements,
formations, personnel et actualités publiés du site actif. La valeur
enregistrée reste une adresse relative (`/formations`) ou une adresse
complète `https://…`.

## 4. Pages publiques

| Adresse | Contenu |
|---|---|
| `/` | accueil : carrousel, sections ordonnables (`admin/home-sections`) |
| `/faculte` | mot du doyen, mission et vision, valeurs, historique, frise, partenaires (bandeau défilant) |
| `/departements`, `/departements/{slug}` | départements par cycle ; organisation des études et maquette PDF |
| `/formations`, `/formations/{slug}` | licences et masters ; parcours d’études, programme des cours |
| `/admissions`, `/faq` | procédure, pièces, gratuité, dates limites ; questions par thème |
| `/recherche` | laboratoires, publications, projets |
| `/corps-enseignant`, `/corps-enseignant/{slug}` | personnel (filtres, pagination par 24) |
| `/actualites`, `/actualites/{slug}`, `/actualites/{slug}/ics` | actualités et événements ; fichier agenda |
| `/alumni`, `/contact`, `/mentions-legales`, `/trouver` | alumni, formulaire de contact, mentions légales, recherche sur le site |
| `/sitemap.xml`, `/robots.txt`, `/healthz` | générés par site ; santé (204) |

Il n’y a ni page Agenda, ni page Partenaires, ni doctorat : les niveaux sont
`licence` et `master`, et le réglage `links.doctoral_studies_url` ajoute un
lien vers le site des études doctorales lorsqu’il est rempli.

### Parcours d’études

Une option de département est une formation (`option_code`). Les
formations d’un même site et d’un même niveau qui portent le même
`common_core` partagent un tronc commun, même entre départements ;
`branch_core` est un second tronc plus étroit. `StudyPathService`
(`service('studyPaths')`) construit l’arbre affiché sur la page du
département et les étapes de la fiche formation ; `ProgrammeModel::studyYears()`
donne la durée (crédits / 60, sinon le nombre lu dans `duration`).

### Thème

`SiteThemeService` dérive la palette de `sites.primary_color` (et
`secondary_color`) ; le layout public émet les variables `--brand*` dans un
`<style {csp-style-nonce}>`. `style.css` ne contient aucune couleur de
marque en dur hors `:root`.

## 5. Langues

- Français : langue source et par défaut. Anglais : public uniquement.
- L’administration, l’authentification et les messages back-office restent
  en français (`/login`, `/logout`, `/admin` forcés en français).
- Priorité côté public (`LocaleFilter`) : paramètre `?lang=` ; cookie
  `site_locale_{slug}` (ou `site_locale_central`) ; en-têtes pays si
  `app.proxyIPs` est renseigné ; `Accept-Language` ; `sites.default_locale` ;
  `fr`.
- La langue par défaut est servie à l’adresse nue, l’autre via `?lang=en`.
  Canonical, hreflang et sélecteur de langue passent par
  `service('localeUrls')` (`LocaleUrlService`). La route
  `/language/{locale}` pose le cookie et redirige vers une adresse sûre.
- Les traductions éditoriales sont dans `content_translations` ; un champ
  anglais vide affiche le français. Les textes fixes (boutons, libellés)
  sont dans `app/Language/{fr,en}/`.
- Les routes restent en français.

## 6. Référencement

- `sitemap.xml` (`SitemapController`) : pages statiques, FAQ si elle a du
  contenu publié, puis formations, départements, personnel et articles du
  site courant, avec alternates de langue.
- `robots.txt` est généré par `SitemapController::robots()` ; le fichier
  statique `public/robots.txt` est supprimé par `sync-instances.sh`.
- JSON-LD : organisation dans le layout, plus `Course`, `Person`, `Event`
  et `FAQPage` selon la page (`StructuredDataService`).
- Mesure d’audience facultative et sans cookie (`AnalyticsService`,
  réglages `analytics.*`, Plausible ou Matomo) ; son origine n’est ajoutée
  à la CSP que si elle est configurée.

## 7. Sécurité (résumé)

Shield, CSRF global, en-têtes sécurisés, CSP (`script-src-attr 'none'`,
`frame-src https://www.google.com` pour la carte), validation serveur et
échappement, uploads vérifiés, limites du formulaire de contact. Détail :
[SECURITE_QUALITE.md](SECURITE_QUALITE.md).

## 8. Médias

- Stockage : `public/uploads/sites/{slug}/{dossier}/` avec noms aléatoires
  (`MediaService`). Images JPG, PNG, WebP de 2 Mo maximum ; PDF de 10 Mo
  maximum (brochures, maquettes, pièces jointes), signature `%PDF-` vérifiée.
- Un fichier téléversé depuis la superadministration est recopié dans le
  dossier public de la faculté (`app.uploadMirrors`, ou `../{slug}/public`
  par défaut).
- `public/uploads/.htaccess` (Apache) et `deploy/nginx/uploads-security.conf`
  (Nginx) interdisent l’exécution de scripts.
- Seul élément graphique partagé : `public/assets/images/logo-placeholder.*`
  (blason de l’université).

## Annexe : schéma de la base

### Tables de la plateforme

| Table | Rôle |
|---|---|
| `sites` | facultés : `identifier`, `name`, `slug`, `hostnames` (JSON), `status`, `default_locale`, `logo`, `primary_color`, `secondary_color`, `theme`, `theme_config`, `menu_config`, `enabled_sections` (ordre des sections d’accueil) |
| `user_sites` | affectation compte ↔ site : `user_id`, `site_id`, `role` (unique par couple) |
| `settings` | réglages (paquet CodeIgniter Settings) étendus par `site_id`, unique `(site_id, key)` |
| `content_translations` | traductions : `site_id`, `resource_type`, `resource_id`, `locale`, `field`, `value` |
| tables Shield | `users`, `auth_identities`, `auth_logins`, `auth_groups_users`, `auth_permissions_users`, `auth_remember_tokens`… |

### Tables de contenu (toutes portent `site_id`)

| Table | Contenu et colonnes notables |
|---|---|
| `home_content` | singleton de l’accueil : présentation, blocs recherche, formations, actualités (libellés, titres, textes, boutons), SEO |
| `home_hero_slides` | carrousel : `image_path`, `alt_text`, `badge`, `title`, `text`, `primary_cta_target/label/url`, `secondary_cta_target/label/url` |
| `site_stats` | chiffres clés (accueil, recherche, alumni) |
| `content_blocks` | blocs d’accueil `custom_text` (mot d’accueil) et `contact_cta` |
| `pages` | textes des pages publiques (`key`, `content` JSON) édités par `PageTextCatalog` |
| `departments` | `name`, `slug`, `abbreviation`, `cycle` (`licence`, `master`, `both`), `description`, `head_staff_id`, `email`, `phone`, `maquette_path`, `maquette_title` |
| `programmes` | `title`, `slug`, `level` (`licence`, `master`), `department_id`, `option_code`, `common_core`, `common_core_years`, `branch_core`, `branch_core_years`, `duration`, `language`, `credits`, `entry_requirement`, `application_deadline`, `curriculum`, `coordinator_staff_id`, `brochure_path`, `apply_url`, `featured_on_home`, `home_order` |
| `staff` | `category`, `department_id`, `name`, `slug`, `photo`, `grade`, `specialty`, `role`, `email`, `biography`, `featured_on_home`, `home_order` |
| `posts` | `type` (`news`, `event`), `title`, `slug`, `excerpt`, `body`, `author_name`, `cover_image`, `attachment_path`, `attachment_name`, `status`, `published_at`, dates et lieu d’événement, `registration_url` |
| `laboratories`, `publications`, `research_projects` | recherche (aucune relation obligatoire projet → laboratoire) |
| `timeline_items`, `partners`, `faq_items` | frise, partenaires (`type`, `logo`, `country`, `url`), FAQ (`category`, `question`, `answer`) |
| `alumni_profiles`, `testimonials` | alumni ; un témoignage peut pointer vers un profil (`SET NULL`) |
| `contact_messages` | messages du formulaire : identité, objet, message, `status`, IP, user-agent, `deleted_at` |
| `home_highlights` | ancienne table « points forts », sans écran d’administration |

Contraintes uniques par site : `posts`, `programmes`, `staff`,
`laboratories`, `departments`, `alumni_profiles` (`site_id`, `slug`) ;
`pages(site_id, key)` ; `research_projects(site_id, code)` ;
`settings(site_id, key)` ;
`content_translations(site_id, resource_type, resource_id, locale, field)`.
Les tables de contenu ont `display_order`, `is_published` ou `status`, et
`deleted_at` (suppression logique) selon le module.

Le seeder `TemplateStarterSeeder` et le provisionnement
(`FacultySiteProvisioningService`) ne créent que du contenu neutre « à
remplacer ». Les données de démonstration des facultés réelles vivent
uniquement dans `FacultyDemoDataService` et `FacultyDemoInformationSeeder`
(`php spark site:seed-demo`).
