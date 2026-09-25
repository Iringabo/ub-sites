# Base De Données

Dernière actualisation documentaire : 11 août 2026.

Ce document décrit le schéma logique réel à partir des migrations présentes dans `app/Database/Migrations/` et des migrations de packages CodeIgniter Shield/Settings.

## Plateforme

- SGBD attendu : MySQL ou MariaDB.
- Moteur : InnoDB.
- Encodage : `utf8mb4`.
- Connexion applicative : groupe `default`.
- Connexion tests : groupe `tests`, base dédiée obligatoire.

## Tables Applicatives

| Table | Rôle | Site-scopée |
|---|---|---|
| `home_content` | contenu singleton de l'accueil | oui |
| `home_hero_slides` | images du carrousel d'accueil | oui |
| `home_highlights` | atouts hérités (affichage public legacy si données présentes ; **plus d’UI admin**) | oui |
| `site_stats` | statistiques d'accueil, recherche, alumni | oui |
| `posts` | actualités et événements | oui |
| `programmes` | formations | oui |
| `staff` | personnel enseignant/administratif | oui |
| `laboratories` | laboratoires | oui |
| `publications` | publications scientifiques | oui |
| `research_projects` | projets de recherche | oui |
| `timeline_items` | repères historiques | oui |
| `alumni_profiles` | profils alumni | oui |
| `testimonials` | témoignages | oui |
| `pages` | pages publiques modifiables | oui |
| `contact_messages` | messages du formulaire contact | oui |
| `content_translations` | traductions éditoriales | oui |
| `sites` | sites/facultés publiables | non |
| `user_sites` | affectations utilisateurs-sites | non |
| `settings` | paramètres CodeIgniter Settings, étendus par `site_id` | oui |

## Multi-Site

`sites` contient les facultés publiables :

- `identifier`, `name`, `slug`
- `hostnames` en JSON/liste
- `status`
- `default_locale`
- `logo`, couleurs et coordonnées
- `theme`, `theme_config`, `menu_config` (JSON)
- `enabled_sections` (JSON : sections d’accueil activées / ordre, géré via
  `/admin/home-sections`)

`user_sites` contient :

- `user_id`
- `site_id`
- `role`
- contrainte unique `(user_id, site_id)`
- clé étrangère vers `sites`
- clé étrangère vers `users` lorsque la table Shield existe au moment de la migration

Les tables éditoriales reçoivent un champ `site_id` obligatoire avec défaut `1`. Les migrations ajoutent des index et des contraintes d'unicité par site, mais ne créent pas de clé étrangère physique `site_id -> sites.id` pour chaque table de contenu.

## Contraintes Uniques Par Site

- `home_content(site_id, singleton_key)`
- `posts(site_id, slug)`
- `programmes(site_id, slug)`
- `staff(site_id, slug)`
- `laboratories(site_id, slug)`
- `laboratories(site_id, abbreviation)`
- `research_projects(site_id, code)`
- `pages(site_id, key)`
- `pages(site_id, slug)`
- `settings(site_id, key)`
- `content_translations(site_id, resource_type, resource_id, locale, field)`

## Contenus Principaux

`home_content` contient les textes de l'accueil : héros, boutons, présentation, recherche, formations, actualités/événements et métadonnées SEO.

`home_hero_slides` contient les images de carrousel : `image_path`, `alt_text`, `display_order`, `is_published`, timestamps et `deleted_at`.

`posts` contient les actualités et événements :

- `type` : `news` ou `event`
- `title`, `slug`, `excerpt`, `body`, `cover_image`
- `status`, `published_at`, `featured`, `home_order`
- champs événement : dates, lieu, URL d'inscription
- SEO, créateur, modificateur, timestamps, `deleted_at`

Une ancienne migration ajoute des colonnes `_en` aux posts pour transition. L'architecture actuelle utilise `content_translations` pour les traductions éditoriales.

`programmes`, `staff`, `laboratories`, `research_projects`, `alumni_profiles` utilisent des slugs propres au site et des champs de publication/ordre.

`programmes`, `staff` et `laboratories` exposent aussi `featured_on_home` et `home_order` pour choisir ce qui apparaît dans les aperçus de la page d’accueil.

`testimonials` peut référencer facultativement `alumni_profiles` par `alumni_profile_id`, avec suppression `SET NULL`.

`pages` contient les pages publiques prédéfinies. Pour la page Faculté (`key=faculty`), la section "Mot du doyen" est stockée dans `content.dean` : photo, nom, fonction, domaine, libellé, titre, paragraphes et signature. Il n'existe pas de table séparée pour ce profil.

## Traductions

`content_translations` centralise les traductions :

- `resource_type`
- `resource_id`
- `locale`
- `field`
- `value`
- `site_id`

L'unicité inclut `site_id`, ce qui permet de traduire la même ressource logique différemment selon le site.

Pour le "Mot du doyen", la traduction anglaise est enregistrée comme traduction du champ `content` de la ressource `pages`. Les champs anglais absents retombent sur le contenu français.

## Messages De Contact

`contact_messages` contient :

- identité déclarée : `name`, `email`, `phone`
- objet et message
- `status` : `new`, `read`, `handled`, `archived`
- métadonnées : IP, user-agent, dates, opérateur `processed_by`
- `deleted_at` pour suppression logique
- `site_id`

## Modules Indépendants

Les migrations suppriment les dépendances obligatoires interdites :

- pas de relation obligatoire `research_projects -> laboratories`
- pas de relation obligatoire `programmes -> staff`

Toute association future entre ces modules devra être facultative, plusieurs-à-plusieurs et explicitement demandée.

## Tables Shield Et Settings

Shield ajoute ses tables d'authentification et d'autorisation, notamment :

- `users`
- `auth_identities`
- `auth_logins`
- `auth_token_logins`
- `auth_remember_tokens`
- `auth_groups_users`
- `auth_permissions_users`

CodeIgniter Settings fournit `settings`, étendue par les migrations applicatives pour l'isolation par site.

## Seeders

`TemplateStarterSeeder` garantit un site par défaut et son contenu de départ neutre via le provisionnement (`FacultySiteProvisioningService`). Ce contenu ne doit pas devenir des valeurs codées en dur dans les vues ou contrôleurs.
