# Règles de travail pour les agents et développeurs

Guide court pour modifier le code. Le fonctionnement complet est dans
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) ; les procédures locales dans
[docs/OPERATIONS_LOCALES.md](docs/OPERATIONS_LOCALES.md).

## Source de vérité

- L’état réel est dans le code : routes, migrations, modèles, services,
  vues, configuration et tests. La documentation suit le code.
- Le code commun se modifie **uniquement dans `template/`**, puis
  `./scripts/sync-instances.sh` (depuis `template/`) recopie `app/`,
  `tests/`, `public/assets/`, `scripts/dev-serve.sh` et la documentation d’exploitation vers les
  instances (fseg, fsi, superadmin, med, fabi, flsh, fs, fpse, fspj).
- Les mots de passe locaux sont dans `LOCAL_CREDENTIALS.md` à la racine de
  la plateforme (ignoré par Git) : ne jamais le committer ni recopier son
  contenu.

## Stack

- PHP 8.2 ou plus, CodeIgniter 4.7, CodeIgniter Shield 1.3, PHPUnit 10.
- MySQL ou MariaDB, InnoDB, `utf8mb4`.
- Bootstrap 5.3, Bootstrap Icons et la police Inter auto-hébergés dans
  `public/assets/vendor/` (aucun CDN).

## Vendor et autoload

- Chaque instance exécute **son propre** `vendor/`. Un `vendor/` partagé par
  lien symbolique sans `composer dump-autoload` fait charger le code d’un
  autre dossier.
- Après modification d’un service ou d’un filtre :
  `php -r "require 'vendor/autoload.php'; echo (new ReflectionClass(App\Services\SiteResolverService::class))->getFileName();"`.

## Architecture

- Routes explicites dans `app/Config/Routes.php` ; pas d’auto-routage.
- Contrôleurs légers ; lecture et assemblage dans les modèles et services.
- `app.siteSlug` désigne le site d’une instance de faculté ;
  `app.centralAdminMode=true` fait d’une copie la superadministration
  (`CentralAdminOnlyFilter`, groupe `superadmin` seulement).
- Menu `/admin` (`AdminNavigationService::sections()`) : Au quotidien,
  Pages du site (un menu par page publique : « Sections de la page » et
  « Listes »), Paramètres du site (`/admin/settings/{identity|contact|social|footer|seo}`),
  Administration ; « Plateforme » en tête sur la superadministration.
- Les textes de page sont définis dans `PageTextCatalog` (source unique du
  menu, de Ctrl+K et de `TextSegmentController`, routes
  `admin/textes/{page}/{section}`). Ajouter un texte modifiable = ajouter un
  champ au catalogue ; un enregistrement ne fusionne que les champs de sa
  section.
- **Liens internes** : un champ de type `url` (catalogue des textes) ou
  `link` (listes de `ResourceController`) s’affiche avec le sélecteur de
  page `admin/partials/link_picker` ; la liste vient de
  `service('sitePageCatalog')->options()` (pages principales, puis
  départements, formations, personnel et actualités publiés du site
  courant). La valeur enregistrée reste un chemin relatif (`/formations`)
  ou une adresse `https://` ; la validation serveur ne change pas. Un
  nouveau champ de lien doit utiliser ce sélecteur, jamais un champ texte
  libre.
- Comptes (`Admin\UserController`) : la superadministration ne crée que des
  administrateurs et éditeurs de faculté. Le groupe `superadmin` et la
  permission `sites.manage` ne s’attribuent jamais depuis l’écran ; un
  superadministrateur se crée par `php spark admin:create-superadmin`. La
  suppression d’un compte est définitive (`delete($id, true)`).
- Médias sous `public/uploads/sites/{slug}/{module}/`, via `MediaService`
  (images 2 Mo, PDF 10 Mo).

## Multi-site

- Les sites sont dans `sites`, les affectations dans `user_sites`.
- Tout contenu dépendant d’un site porte `site_id` ; lire avec
  `SiteScopedModel::forSite()`. Slugs et clés uniques par site.
- Pas de lien obligatoire entre projets et laboratoires, ni entre
  formations et personnel.
- Aucun repli codé en dur vers un slug de faculté dans les services.

## Thème public

- La palette dérive de `sites.primary_color` (et `secondary_color`) via
  `SiteThemeService` ; le layout émet les variables `--brand*` dans
  `<style {csp-style-nonce}>` (le placeholder devient l’attribut complet ;
  ne pas écrire `nonce="{csp-style-nonce}"`).
- Dans `style.css`, aucune couleur de marque en dur : `var(--brand)`,
  `--brand-strong` (texte et boutons, contraste AA), `--brand-dark`,
  `--brand-deep`, `--brand-tint`, `--brand-soft`, `--accent`. Dans les vues,
  préférer `.text-brand` à `text-success`.
- Chaque couleur de faculté est une nuance du vert ou du rouge du logo de
  l’Université ; les couleurs de démonstration sont dans
  `FacultyDemoDataService::BRAND_COLORS`.
- Sections d’accueil : `HomeSectionsController::availableSections()` ; ordre
  par défaut : `FacultyDemoDataService::DEFAULT_SECTION_ORDER`.

## Langues

- Français : langue source et par défaut ; l’anglais est servi via
  `?lang=en` (prioritaire sur cookie et navigateur dans `LocaleFilter`).
- Administration, authentification et messages internes en français.
- Traductions éditoriales dans `content_translations` ; repli sur le
  français. Libellés fixes dans `app/Language/{fr,en}/`.
- Canonical, hreflang et sélecteur de langue : `service('localeUrls')`.

## Référencement

- `robots.txt` est généré par `SitemapController::robots()` (ne pas
  recréer `public/robots.txt`, que la synchronisation supprime) ;
  `sitemap.xml` ne liste que le site courant.
- JSON-LD : organisation dans le layout, plus `$structuredData` de la vue
  (`Course`, `Person`, `Event`, `FAQPage`) dans
  `<script type="application/ld+json" {csp-script-nonce}>`.
- Pas de page Agenda ni de page Partenaires (bandeau défilant sur La
  Faculté) ; seul `actualites/{slug}/ics` produit un fichier calendrier.
- Mesure d’audience facultative, sans cookie (`AnalyticsService`) ; son
  origine n’entre dans la CSP que si elle est configurée.

## Contenus

- Corps de texte en paragraphes séparés par une ligne vide, rendus par
  `site_paragraphs()` ; `site_plain_paragraphs()` retire les `<p>`/`<br>`
  collés (à l’enregistrement et pour les extraits). Toujours échapper.
- Formations : niveaux `licence` et `master` seulement (pas de doctorat ;
  lien externe `links.doctoral_studies_url`, masqué s’il est vide). Pas de
  frais : champ `entry_requirement`. Une option est une formation à part
  (`option_code`). Troncs communs : `common_core` (+ `common_core_years`),
  `branch_core` (+ `branch_core_years`), comparés sur les valeurs françaises
  (`ProgrammeModel::commonCorePeerIds()`, `branchCorePeerIds()`) ; arbre
  construit par `StudyPathService`.
- Départements : `cycle` (`licence`, `master`, `both`), maquette PDF
  facultative (`maquette_path`, `maquette_title`).
- Articles : `author_name`, pièce jointe PDF facultative.
- Mentions légales : textes génériques neutres quand les champs sont vides.
- Le modèle reste **neutre** : aucune donnée réelle de faculté dans les
  migrations, `TemplateStarterSeeder` ou les vues. Les données réelles ne
  vivent que dans les services de démonstration (`FacultyDemoDataService`,
  `FacultyDemoInformationSeeder`).

## Sécurité

- Garder CSRF, en-têtes de sécurité et CSP ; aucun style ni script en
  ligne : JavaScript dans `public/assets/js/`, styles dans
  `public/assets/css/style.css`.
- Valider côté serveur, échapper avec `esc()`.
- Aucune suppression par GET ; schéma modifié uniquement par migration.
- Ne jamais versionner `.env`, secrets, clés ou exports de base ; ne jamais
  révéler les valeurs du `.env` ; ne pas copier un `.env` entre instances.
- Ne pas modifier `vendor/`.

## Commandes utiles

```bash
composer validate
php spark routes
php spark migrate:status
php spark site:seed-demo --force          # depuis superadmin/
php spark admin:create-faculty-admin      # instance de faculté
php spark admin:create-superadmin         # superadmin/ seulement
php spark app:production-check --strict
php spark testdb:migrate
php vendor/bin/phpunit --configuration phpunit.dist.xml --no-logging tests/feature/AdminTextSegmentsTest.php
```

`database.tests.database` doit être une base dédiée : le bootstrap des
tests la supprime et la recrée. Lancer un fichier de test à la fois.

## Git

- Petits lots cohérents ; préserver les modifications existantes d’autres
  contributeurs.
- Aucun commit sans demande explicite ; ne pas réécrire l’historique.

## Définition de terminé

- Modèle neutre ; routes, permissions, validation, vues et tests cohérents.
- Admin et authentification en français ; public bilingue quand c’est
  pertinent.
- Isolation multi-site respectée.
- Migration et données de démonstration fournies si le schéma change.
- Synchronisation lancée, tests concernés passés, documentation à jour.
