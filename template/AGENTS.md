# Instructions Pour Agents Codex

Ce fichier est un guide opérationnel court pour les futurs agents. Il ne remplace pas la documentation complète dans `docs/`.

## Source De Vérité

- Pour l'état actuel, lire le code, les migrations, les routes, les modèles, les services, les vues, la configuration et les tests.
- Lire d'abord [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/CREER_UN_SITE.md](docs/CREER_UN_SITE.md), [docs/MULTI_FOLDER_DEPLOYMENT.md](docs/MULTI_FOLDER_DEPLOYMENT.md), [docs/LOCAL_OPERATIONS.md](docs/LOCAL_OPERATIONS.md) et [docs/README.md](docs/README.md).
- Identifiants locaux de la plateforme : `LOCAL_CREDENTIALS.md` à la racine parent (gitignored ; ne jamais committer).

## Mission Du Dépôt

- Ce dépôt est le **modèle (template)** de la plateforme, pas un site en production.
- On en copie manuellement deux types d'instances : un **dossier par faculté** (site public + administration de cette seule faculté) et un **dossier superadministration** (le superadmin choisit une faculté et en édite le contenu ici).
- Menu `/admin` (`AdminNavigationService::sections()`) en quatre sections :
  Au quotidien, Pages du site (un menu déroulant par page publique :
  « Sections de la page » + « Listes »), Paramètres du site
  (`/admin/settings/{identity|contact|social|footer|seo}`), Administration
  (+ Plateforme en tête sur superadmin). L’admin facultaire a
  `settings.manage` et crée un **administrateur** ou un **éditeur** de *sa*
  faculté. Pas de création de site depuis l’UI. Modules retirés de l’UI :
  pages, blocs, points forts, textes des sections (`home-content`).
- Les textes de page sont de petites catégories définies dans
  `PageTextCatalog` (source unique pour le menu, Ctrl+K et
  `TextSegmentController`, routes `admin/textes/{page}/{section}`). Ajouter
  un texte éditable = ajouter un champ au catalogue ; un enregistrement ne
  fusionne que les champs de sa catégorie (FR + JSON EN complet).
- Une seule base de données est partagée par toutes les instances ; l'isolation des contenus repose sur `site_id`.
- Aucune identité ou contenu FSEG réel ne doit être figé dans le modèle : contenu de démarrage neutre uniquement, généré par provisionnement (`FacultySiteProvisioningService`, `php spark site:create`).

## Stack

- PHP `^8.2`
- CodeIgniter 4.7.x installé avec Composer
- CodeIgniter Shield 1.3.x
- MySQL/MariaDB, InnoDB, `utf8mb4`
- Bootstrap 5, Bootstrap Icons et la police Inter sont auto-hébergés dans `public/assets/vendor/` (aucun CDN ; CSP `'self'`)
- PHPUnit 10

## Vendor Et Autoload

- Chaque instance exécute TOUJOURS son propre `vendor/` : ne jamais partager
  le dossier vendor par symlink sans relancer `composer dump-autoload` dans
  la copie, sinon l'autoload `App\` pointe vers le parent réel du symlink et
  le code exécuté n'est pas celui du dossier courant.
- Après modification d'un service/filtre, vérifier avec
  `php -r "require 'vendor/autoload.php'; echo (new ReflectionClass(App\Services\SiteResolverService::class))->getFileName();"`
  que la classe chargée est bien celle de ce dépôt.
- Recopier `app/`, `tests/` et `public/assets/` vers les instances :
  `./scripts/sync-instances.sh` depuis `template/`.

## Architecture

- Routes explicites dans `app/Config/Routes.php`; ne pas activer l'auto-routage historique.
- Contrôleurs légers, logique de lecture/assemblage dans modèles ou services.
- Administration sous `/admin`, protégée par Shield, groupes et permissions.
- Médias publics sous `public/uploads/{slug}/`, avec protection serveur.
- `app.siteSlug` désigne le site servi par une instance facultaire ; `app.centralAdminMode=true` transforme une copie en instance superadmin (`CentralAdminOnlyFilter`).
- Séparation stricte des connexions : le personnel d'une faculté n'administre que via le dossier de sa faculté ; l'instance superadmin est réservée au groupe Shield `superadmin`; le superadmin conserve l'accès aux `/admin` facultaires.

## Multi-Site

- Les sites facultaires sont dans `sites`; les affectations admin dans `user_sites`.
- Les contenus site-dépendants portent `site_id`.
- Utiliser `SiteScopedModel::forSite()` pour les lectures de contenu.
- Ne pas créer de relation obligatoire entre projets et laboratoires, ni entre programmes et personnel.
- Ne réintroduire aucun repli codé en dur vers un slug de site particulier dans les services.

## Thème Public

- La palette d'une faculté dérive de `sites.primary_color` (et `secondary_color`) via `SiteThemeService` ; le layout public émet les variables `--brand*` dans un `<style {csp-style-nonce}>` (le placeholder est remplacé par l'attribut `nonce="…"` complet ; ne pas écrire `nonce="{csp-style-nonce}"`).
- Dans `public/assets/css/style.css`, n'écrire aucune couleur de marque en dur : utiliser `var(--brand)`, `--brand-strong` (texte et boutons, contraste AA garanti), `--brand-dark`, `--brand-deep`, `--brand-tint`, `--brand-soft`, `--accent`. Les alias `--green`, `--green-dark`, `--red` restent disponibles. Dans les vues, préférer `.text-brand` à `text-success`.
- Chaque couleur de faculté doit être une nuance du vert ou du rouge du logo de l'Université du Burundi ; l'accent est automatiquement l'autre couleur du logo. Les valeurs par défaut du seed démo sont dans `FacultyDemoDataService::BRAND_COLORS` ; le provisioning et le repli restent le vert institutionnel.
- Les sections d'accueil disponibles sont dans `HomeSectionsController::availableSections()` ; l'ordre par défaut (provisioning et seed) dans `FacultyDemoDataService::DEFAULT_SECTION_ORDER`. Les libellés fixes (accès rapides, boutons) vivent dans `app/Language/{fr,en}/Site.php` et `Home.php`.

## Langues

- Le français est la langue source et la locale par défaut.
- Le public peut afficher le français ou l'anglais.
- L'administration, l'authentification et les messages back-office restent en français.
- Les traductions éditoriales anglaises sont dans `content_translations`; le fallback est le contenu français.

## Sécurité

- Garder CSRF, secure headers et CSP.
- Valider les formulaires côté serveur et échapper les sorties.
- Ne jamais stocker de secrets dans Git ; ne jamais copier le `.env` réel d'une instance vers une autre.
- Ne jamais révéler les valeurs présentes dans `.env` ou `env`.

## Commandes Utiles

```bash
composer validate
php spark routes
php spark migrate:status
# Uniquement dans ce dossier modèle (voir docs/CREER_UN_SITE.md) :
php spark site:create --identifier fsi --slug fsi --name="Faculté des Sciences et Ingénierie"
# Superadmin : uniquement si app.centralAdminMode=true (dossier superadmin)
php spark admin:create-superadmin
# Admin de faculté : uniquement si app.centralAdminMode=false et app.siteSlug renseigné
php spark admin:create-faculty-admin
php spark app:production-check
php spark test
```

Avant `php spark test`, vérifier que `database.tests.database` est une base dédiée : le bootstrap des tests la supprime et la recrée.

## Définition De Terminé

- Le modèle reste neutre : aucune donnée de faculté réelle ajoutée aux migrations, seeders ou vues du dépôt.
- Routes, permissions, validation, vues et tests pertinents sont cohérents.
- Les contenus visibles sont français côté admin/auth et bilingues côté public lorsque la fonctionnalité le permet.
- L'isolation multi-site est respectée (un admin facultaire ne voit que son site).
- Les migrations et le provisionnement accompagnent les changements de schéma/données.
- La documentation concernée est mise à jour.
- Aucun commit n'est créé sauf demande explicite.
