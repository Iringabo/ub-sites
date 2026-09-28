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

1. lire les candidats host depuis `Host` et `HTTP_HOST` (pas `SERVER_NAME`) ;
2. lire `X-Forwarded-Host` seulement si `app.proxyIPs` est renseigné ;
3. chercher une correspondance dans `sites.hostnames` parmi les sites actifs;
4. si `app.requireKnownHostname=true`, répondre 404 lorsqu'aucun site actif ne correspond à l'hôte;
5. utiliser `app.siteId` si configuré;
6. utiliser `app.siteSlug` (obligatoire sur une instance facultaire) ;
7. si la base est indisponible et qu’un `app.siteSlug` est défini, échouer (404) plutôt que d’usurper un site `id=1`.

En développement local, chaque faculté est servie sur son propre port (`scripts/dev-serve.sh`) : fseg=8101, fsi=8102, superadmin=8103, med=8104, fabi=8105, flsh=8106. Pour réserver l'affichage public aux domaines configurés en production, activer `app.requireKnownHostname=true`. Un dossier facultaire dont `app.siteSlug` ne correspond à aucun site actif répond 404 explicite.

Pour l'administration :

1. utiliser `active_admin_site_id` en session si l'utilisateur y a accès;
2. utiliser le site correspondant au hostname courant si l'utilisateur y a accès;
3. utiliser le site par défaut si autorisé;
4. sinon prendre le premier site disponible pour l'utilisateur.

Renseigner `app.allowedHostnames` (ex. `localhost,127.0.0.1` en local, ou les domaines publics en production) pour que `site_url()` et `base_url()` génèrent des URLs sûres.

`SiteResolverService` et `AdminAccessService` partagent le même filtre de proxy (`App\Support\TrustedProxies`) : sans `app.proxyIPs`, `X-Forwarded-Host` est ignoré.

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
- Les catégories de texte `/admin/textes/{page}/{section}` (par exemple `/admin/textes/faculte/mot-du-doyen`) modifient la page du site actif uniquement (`pages.key` du site courant, ou `home_content` pour l'accueil). L'ancienne adresse `/admin/faculty/profile` redirige vers Mot du doyen.
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

Les photos de doyens envoyées depuis La Faculté › Mot du doyen utilisent le dossier `faculty-deans` sous le site actif ; les images de bandeau utilisent `banners`.

Les anciens chemins restent lisibles pour éviter une migration destructive.

## Tests

`tests/feature/MultiSiteIsolationTest.php` vérifie :

- résolution publique par hostname;
- même slug de formation sur deux sites;
- filtrage d'une liste admin par site actif;
- refus de sélection d'un site non affecté.
