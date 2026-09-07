# Audit architectural et UX — page d'administration FSEG

Date : 2026-08-27
Périmètre : instance facultaire `fseg/` (administration `/admin`) et intégration
avec l'instance superadmin `superadmin/` (tableau de bord central).
Méthode : lecture des routes (`app/Config/Routes.php`), du layout
(`app/Views/layouts/admin.php`), des contrôleurs/vues admin, des services
`AdminAccessService`, `SiteResolverService`, `AdminDashboardService`, des
filtres `AdminAccessFilter`/`CentralAdminOnlyFilter`, de la configuration de
session (`app/Config/Session.php`, `Auth.php`) et des `.env` des deux instances.

État de référence : `fseg/` et `superadmin/` sont des copies du même modèle
(identiques). Le modèle `template/` est en avance sur les instances pour
l'onboarding (`AdminDashboardService`, `AdminExitGuardFilter`). Les instances
sont donc en retard fonctionnellement sur l'onboarding.

---

## 1. Redesign structurel (IA + layout, charge cognitive, onboarding)

### Constats

- **Navigation à 8 groupes repliables** codée en dur dans la vue
  (`app/Views/layouts/admin.php:24-85`). Les groupes *Accueil, Contenu,
  Faculté, Messagerie, Sécurité, Recherche, Communauté, Configuration* imposent
  une hiérarchie large et peu orientée tâche. Pour un nouvel admin, choisir
  « où créer une actualité » exige de comprendre la segmentation éditoriale
  (posts vs pages vs content-blocks vs timeline-items) avant de pouvoir agir.
- **L'IA n'est pas pilotée par les rôles.** Le filtrage des liens se fait
  côté vue via `$canUseLink` (`admin.php:87-95`), mais la *structure* des
  groupes est identique pour un éditeur, un admin de faculté et un superadmin.
  Un éditeur voit les mêmes 8 groupes qu'un site_admin, dont plusieurs vides
  ou sans rapport avec son périmètre.
- **Aucun parcours d'onboarding.** Le tableau de bord (`dashboard.php`)
  affiche « Bienvenue, {email} » et des cartes statistiques, mais aucune
  liste de tâches prioritaires, aucun état vide orienté action, aucune
  checklist de première connexion. Un nouvel admin ne sait pas « par quoi
  commencer ».
- **Duplication des points d'entrée.** Les raccourcis du dashboard
  (`dashboard.php:131-135`) répètent les liens de la navigation sans hiérarchie
  claire (les mêmes boutons « Créer une actualité » apparaissent deux fois).
- **Pas de recherche globale, pas de fil d'Ariane, pas de persistance de
  l'état replié**, pas de badge de tâches en attente sur la navigation. Le
  titre courant (`admin.php:158-164`) se contente de répéter le titre de page.
- **Switcher de site non disponible pour les admins de faculté.**
  `admin.php:167` n'affiche le sélecteur que si `count($availableSites) > 1`
  **et** l'utilisateur est superadmin. Sur une instance mono-faculté, un
  admin de faculté n'a ni switcher ni repère visuel de « site courant » au-delà
  du libellé dans la barre supérieure. C'est correct pour l'isolement, mais
  l'absence de repère renforce le sentiment d'interface « boîte à outils » sans
  cap.

### Proposition d'IA revisité (par tâche, non par module technique)

Regrouper les 8 groupes en **3 zones de travail** + un tableau de bord persistant :

| Zone | Contenu (actuel) | Logique |
|------|------------------|---------|
| **Tableau de bord** | `admin` | Point d'entrée unique, piloté par rôle |
| **Contenu & Communication** | home-content, hero, highlights, stats, posts (news/events), pages, content-blocks, timeline, faculty/profile, messages | Tout ce qui produit la page publique |
| **Communauté & Recherche** | staff, alumni, testimonials, laboratories, publications, research-projects | Contenus relationnels/scientifiques |
| **Administration & Sécurité** | users, settings, sites | Gestion des accès et de l'instance |

- **Navigation pilotée par un service** (`AdminNavigationService`) au lieu
  d'un tableau figé dans la vue. Cela garantit une IA unique et cohérente
  entre `fseg/`, `fsi/` et `superadmin/`, et centralise la logique de
  permission (supprime `$canUseLink` du template).
- **Niveaux adaptés au rôle** : un *éditeur* ne voit que « Contenu &
  Communication » + dashboard ; un *site_admin* voit aussi « Administration &
  Sécurité » (users) ; le *superadmin* voit « sites » et le dashboard central.
- **Barre d'actions contextuelle collante** par module (ex. « Nouvelle
  actualité » toujours visible dans Posts) pour réduire le déplacement visuel.
- **Recherche/filtre global** dans la sidebar + **fil d'Ariane** + **badges
  de tâches** (ex. nombre de messages non lus) pour guider l'attention.

### Proposition d'onboarding pour nouveaux administrateurs

- **Checklist de première connexion** sur le dashboard (états vides orientés
  action) : « Complétez la présentation de la faculté », « Ajoutez un membre
  du personnel », « Rédigez votre première actualité », « Vérifiez les
  paramètres ». Barre de progression. À alimenter depuis
  `AdminDashboardService` (déjà présent dans `template/`, à rétablir dans
  l'instance).
- **Dashboard piloté par rôle** : tâches éditoriales pour les éditeurs,
  gestion des utilisateurs pour les site_admins, vue transversale pour le
  superadmin.
- **Aide contextuelle inline** (tooltips « ? ») par module — l'administration
  restant volontairement en français (`AGENTS.md`), l'aide ne doit pas être
  traduite mais toujours accessible.
- **États vides avec CTA** plutôt que des cartes vides (ex. « Aucun message »
  → « La boîte de réception est propre » avec lien vers la config).

### Ergonomie de layout

- Conserver la sidebar repliable mais **persister l'état ouvert** (localStorage)
  et **développer par défaut le groupe contenant la tâche en attente**.
- Améliorer l'off-canvas mobile avec overlay + piège de focus (accessibilité,
  déjà amorcé via `skip-link` et `admin.js`).
- Ajouter une **ligne de description** sous le titre courant (ex. « Gérez les
  actualités et événements publiés sur le site ») pour réduire l'ambiguïté
  sémantique des libellés courts.

---

## 2. Audit de connectivité (admin FSEG ↔ dashboard superadmin)

Les deux instances partagent une seule base (`ub_shared`), isolation par
`site_id` (`UserSiteModel`, `SiteScopedModel::forSite()`). Elles sont
physiquement séparées (dossiers + `vendor/` distincts) et communiquent
uniquement via la base de données.

### C1 — [HAUTE] Pas de SSO : le lien « Administrer » force une seconde connexion

Dans le dashboard central, l'action « Administrer » pointe vers
`https://{firstHost}/admin`
(`superadmin/app/Views/admin/central/dashboard.php:104`). Cela ouvre
l'instance facultaire **physiquement distincte**, avec sa propre boutique de
sessions et son propre cookie de session. Aucune délégation de session, aucun
token de rebond, aucun SSO n'est implémenté : un superadmin connecté au
central doit **se reconnecter** sur chaque instance facultaire. C'est le
principal goulot de connectivité et une friction forte pour le support
multi-facultés.

### C2 — [MOYENNE] Collision de nom de cookie de session en déploiement same-domain

Les deux instances utilisent `cookieName = 'ci_session'` par défaut
(`app/Config/Session.php:34`) et, en local, le même domaine `localhost`
(ports 8101 / 8103). Les cookies sont scopés par domaine (et non par port) :
dans un déploiement same-domain, le cookie `ci_session` d'une instance
**écrase** celui de l'autre, provoquant des déconnexions croisées et une
expérience confuse. En production (sous-domaines distincts :
`admin.ub.edu.bi` vs `fsi.ub.edu.bi`) le risque disparaît, mais la fragilité
n'est documentée nulle part et se manifeste dès le dev/local.

### C3 — [MOYENNE] Divergence entre le périmètre d'accès et le contexte de travail

`AdminAccessFilter::before` valide `instanceBoundSite($request)`
(`AdminAccessFilter.php:29-35`) — le site lié à **l'hôte** — alors que le
switcher superadmin modifie `session('active_admin_site_id')` vers un site
**différent** (`SiteResolverService::selectAdminSite`,
`SiteResolverService.php:100-110`). Pour un superadmin sur une instance
facultaire, le filtre valide le site lié à l'hôte (toujours OK pour un
superadmin) tandis que le site réellement édité diverge. La porte d'accès et
le contexte de travail se désynchronisent : c'est conceptuellement fragile et
peut masquer un vrai contrôle de permission. Chez un admin de faculté le
switcher est masqué (`admin.php:167`), donc pas de conflit en pratique, mais
l'écart architectural reste.

### C4 — [FAIBLE] Pas de synchronisation de l'état « site courant »

`SiteController::select` ne réinitialise que `settingsService` et
`contentTranslationService` (`SiteController.php:22-23`) ; l'état
`active_admin_site_id` vit en session **propre à l'instance**. Changer de site
dans l'instance facultaire ne se reflète pas dans le central et vice versa.
C'est sain pour la sécurité, mais le superadmin ne peut pas **transporter son
contexte** dashboard → facultaire (cf. C1), ce qui multiplie les allers-retours.

### C5 — [FAIBLE] Porte d'accès centrale vs affectations `user_sites`

`AdminAccessService::canAccessFacultyAdmin` croise `admin.access` et le rôle
`site_admin`/`editor` dans `user_sites` (`AdminAccessService.php:69-85`),
tandis que `AdminAccessFilter` teste `instanceBoundSite`. Si un déploiement
mappe un même dossier sur plusieurs hôtes de sites, le contrôle par site
affecté pourrait diverger du site lié à l'hôte. Conflit de permission latent,
à verrouiller en testant `canAccessFacultyAdmin` sur le site *sélectionné*,
pas seulement sur le site lié à l'hôte.

### Recommandations connectivité

- **C1/C4** : implémenter un rebond SSO léger (token à usage unique signé,
  conservé en base `ub_shared`, échangé contre une session sur l'instance
  facultaire) pour que « Administrer » ouvre directement la fac sans
  reconnexion. Alternative minimale : lien profond vers `/login` pré-rempli du
  domaine + message. Documenter clairement l'absence de SSO sinon.
- **C2** : rendre `cookieName`/`cookieDomain` **dépendant de l'instance**
  (ex. `ci_session_fseg`, `ci_session_central`) via `.env`, et activer
  `forceGlobalSecureRequests=true` + `cookieSameSite` strict en production.
- **C3/C5** : faire valider le filtre sur le `site_id` **effectif** (sélectionné
  en session pour superadmin, sinon lié à l'hôte) afin que porte d'accès et
  contexte coïncident.

---

## 3. Optimisation de la gestion des sessions

### Constats

- `app/Config/Session.php` : `$expiration = 7200` (2 h),
  `$timeToUpdate = 300`, `$regenerateDestroy = false`, `$matchIP = false`,
  driver `FileHandler`.
- Dans CodeIgniter 4, `$expiration` fonctionne comme une **fenêtre glissante
  d'inactivité** (rafraîchie à chaque requête), **sans plafond absolu**. Une
  session peut donc théoriquement durer indéfiniment tant que l'utilisateur
  reste actif → aucune durée de vie maximale garantie (écart de sécurité).
- **2 h d'inactivité est très long** pour un panneau d'administration : dans
  un bureau partagé, un admin qui s'éloigne reste connecté ~2 h.
- **Aucun avertissement avant expiration**, aucune détection d'inactivé,
  aucune invite « prolonger la session » → interruption nette en plein travail
  (ex. rédaction longue). `admin.js` protège la perte par `beforeunload`, mais
  les données non soumises sont perdues au timeout.
- Shield permet « remember me » (`Auth.php` `$sessionConfig.rememberLength =
  30*DAY`, `allowRemembering=true`), mais aucun flux dédié pour l'admin.
- `forceGlobalSecureRequests=false` en `.env` : le cookie de session n'est pas
  forcé en Secure en local (acceptable en dev, à sécuriser en prod).

### Nouvelle politique proposée

| Paramètre | Actuel | Proposé | Justification |
|-----------|--------|---------|---------------|
| Expiration (inactivité glissante) | 7200 s (2 h) | **1800 s (30 min)** | Réduit la fenêtre d'exposition en poste partagé ; 30 min reste confortable pour une tâche admin. |
| Plafond absolu (durée de vie max) | aucun | **28800 s (8 h)** | Force une ré-authentification après une journée complète ; couvre le manque de plafond actuel. |
| Avertissement avant expiration | aucun | **alerte à T-5 min** | Modal « Rester connecté » (heartbeat) évite l'interruption en pleine saisie. |
| `timeToUpdate` | 300 s | 300 s (conservé) | Régénération d'ID toutes les 5 min, correct. |
| `regenerateDestroy` | false | **true** | Évite la persistance de vieilles sessions après régénération. |
| Remember-me (admin) | 30 j | **désactivé par défaut** pour le staff ; option 1 j sur poste de confiance, avec ré-auth sur action sensible | Limite la survie des sessions volées. |
| Cookie | `ci_session` | **par instance** + Secure + SameSite=Strict en prod | Voir C2. |

Implémentation côté application :
- Stocker le `active_admin_site_id` **et** un `login_at`/`last_activity` en
  session ; un filtre léger vérifie le plafond absolu (8 h) et déclenche la
  modal d'avertissement via un heartbeat JS (ping `/healthz` ou endpoint
  dédié) pour maintenir la fenêtre glissante pendant la rédaction.
- Sur expiration : redirection vers `/login` avec message « Votre session a
  expiré pour votre sécurité » et conservation de l'URL cible (post-login
  return-to).
- Pour les **utilisateurs généraux** (lecteurs du site public, non admin), ne
  pas appliquer ces plafonds : leur parcours n'ouvre pas de session
  privilégiée, donc la politique stricte ne vise que `/admin`. Cela évite
  toute interruption de consultation du site public tout en maintenant les
  protocoles de sécurité sur l'espace sensible.

---

## Synthèse des actions prioritaires

1. Extraire la navigation dans un `AdminNavigationService` (IA unique,
   pilotée par rôle) — composant 1.
2. Ajouter l'onboarding (checklist + dashboard par rôle) depuis
   `AdminDashboardService` déjà présent dans `template/` — composant 1.
3. Implémenter un rebond SSO léger ou documenter l'absence, et nommer les
   cookies de session par instance — composant 2 (C1, C2).
4. Faire valider `AdminAccessFilter` sur le site *sélectionné* — composant 2
   (C3, C5).
5. Appliquer la politique de session (30 min inactivité, 8 h plafond, alerte
   T-5 min, cookie par instance, Secure/SameSite) sur `/admin` uniquement —
   composant 3.
