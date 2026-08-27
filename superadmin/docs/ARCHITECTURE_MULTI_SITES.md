# Architecture Multi-Sites

Dernière actualisation documentaire : 11 août 2026.

L'application utilise une seule base pour plusieurs sites facultaires partageant le même code, le même design et la même administration.

## Tables

- `sites` : facultés publiables, domaines, statut, langue par défaut, logo, couleurs et coordonnées.
- `user_sites` : affectations des utilisateurs aux sites administrables.
- `site_id` : champ présent sur les contenus éditoriaux, paramètres, messages et traductions.

## Résolution Du Site Actif

`SiteResolverService` applique deux modes.

Pour le public :

1. lire les candidats host depuis `Host`, `HTTP_HOST`, `SERVER_NAME` et `X-Forwarded-Host`;
2. chercher une correspondance dans `sites.hostnames` parmi les sites actifs;
3. si `app.requireKnownHostname=true`, répondre 404 lorsqu'aucun site actif ne correspond à l'hôte;
4. utiliser `app.siteId` si configuré;
5. utiliser `app.siteSlug`, par défaut `fseg`;
6. retomber sur le site FSEG par défaut.

En développement local, chaque faculté est servie sur son propre port (`scripts/dev-serve.sh`) : fseg=8101, fsi=8102, superadmin=8103. Pour réserver l'affichage public aux domaines configurés en production, activer `app.requireKnownHostname=true`. Un dossier facultaire dont `app.siteSlug` ne correspond à aucun site actif répond 404 explicite.

Pour l'administration :

1. utiliser `active_admin_site_id` en session si l'utilisateur y a accès;
2. utiliser le site correspondant au hostname courant si l'utilisateur y a accès;
3. utiliser le site par défaut si autorisé;
4. sinon prendre le premier site disponible pour l'utilisateur.

Renseigner `app.allowedHostnames` (ex. `localhost,127.0.0.1` en local, ou les domaines publics en production) pour que `site_url()` et `base_url()` génèrent des URLs sûres.

Le service lit directement `X-Forwarded-Host`; il faut donc ne laisser parvenir ce header à PHP que depuis un proxy de confiance. `app.proxyIPs` reste nécessaire pour la confiance accordée par CodeIgniter aux informations proxy, mais il ne filtre pas à lui seul la lecture de `X-Forwarded-Host` par `SiteResolverService`.

## Isolation Des Données

Les modèles de contenu héritent de `SiteScopedModel`. Les lectures publiques et administratives doivent appeler `forSite()` lorsqu'elles manipulent des contenus dépendants du site actif.

Les insertions ajoutent automatiquement `site_id` si la table est site-scopée et si le contrôleur ne le fournit pas.

Tables site-scopées :

- `home_content`
- `home_hero_slides`
- `home_highlights`
- `site_stats`
- `posts`
- `programmes`
- `staff`
- `laboratories`
- `publications`
- `research_projects`
- `timeline_items`
- `alumni_profiles`
- `testimonials`
- `pages`
- `contact_messages`
- `settings`
- `content_translations`

## Administration

- Le sélecteur de site apparaît dans le layout admin lorsqu'un utilisateur a plusieurs sites disponibles.
- L'écran `/admin/faculty/profile` modifie le "Mot du doyen" de la page Faculté du site actif uniquement. Il utilise la page `pages.key=faculty` du site courant.
- `sites.manage` donne accès au module `/admin/sites`.
- Les superadministrateurs peuvent voir tous les sites actifs.
- Les autres administrateurs sont limités à leurs entrées `user_sites`.
- Les suppressions de sites sont désactivées dans le module actuel.

## Contraintes D'Unicité

Les slugs et clés sont uniques par site pour éviter les collisions entre facultés :

- posts, programmes, staff, laboratoires, projets, pages;
- paramètres;
- traductions;
- singleton d'accueil.

## Médias

Les nouveaux médias sont stockés sous :

```text
public/uploads/sites/{slug-du-site}/{module}/...
```

Les photos de doyens envoyées depuis l'écran `/admin/faculty/profile` utilisent le dossier `faculty-deans` sous le site actif.

Les anciens chemins restent lisibles pour éviter une migration destructive.

## Tests

`tests/feature/MultiSiteIsolationTest.php` vérifie :

- résolution publique par hostname;
- même slug de formation sur deux sites;
- filtrage d'une liste admin par site actif;
- refus de sélection d'un site non affecté.
