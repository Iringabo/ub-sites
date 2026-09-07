# Audit multi-modules & exécution simulée — FSEG / FSI / Template

Date : 2026-08-27
Périmètre : `fseg/`, `fsi/`, `template/` (et référence `superadmin/`), déploiement
multi-dossiers, base partagée `ub_shared`, isolation par `site_id`.
Méthode : diff des arborescences `app/`, lecture de `SiteResolverService`,
`AdminAccessService`, `SettingsService`, `AdminDashboardService`, `SiteScopedModel`,
`ResourceController`, `FacultySiteProvisioningService`, `Config/Session.php`,
`Config/Settings.php`, `Config/Filters.php`, et inspection du schéma réel de
`ub_shared` (colonnes + index).

---

## 1. Implémentation de l'architecture admin FSEG → FSI / Template

### Constat de divergence (réel)

```
diff -rq fseg/app fsi/app            → AUCUNE différence (copie byte-identique)
diff -rq fseg/app template/app       → 8 fichiers divergent + 1 fichier en plus
```

- **FSI est byte-identique à FSEG**. L'architecture administrative (contrôleurs
  `app/Controllers/Admin/*`, vues `app/Views/admin/*`, services `AdminAccessService`,
  `SiteResolverService`, `AdminDashboardService`, filtres `AdminAccessFilter`,
  `CentralAdminOnlyFilter`, routes `app/Config/Routes.php`) y est **déjà présente
  à l'identique**. Aucune propagation n'est nécessaire.
- **Template est un SUR-ENSEMBLE de FSEG** : il ajoute `AdminExitGuardFilter.php`
  (déconnexion à la sortie de `/admin`), `ResourceController::centralContentGuard`
  (la superadministration centrale ne modifie pas le contenu éditorial) et
  `AdminDashboardService::onboarding()`. FSEG/FSI **ne possèdent pas** ces trois
  avancées.

### Décision d'implémentation

Recopier FSEG **par-dessus** Template détruirait `AdminExitGuardFilter`,
`centralContentGuard` et `onboarding()` → **régression fonctionnelle et de
sécurité**. Je n'ai donc **pas** copié FSEG sur Template. Réciproquement, FSI
n'avait rien à recevoir.

**Conclusion de l'étape « implémentation »** : l'architecture administrative de
FSEG est déjà présente dans FSI (identique) et dans Template (en version
enrichie). Aucune modification de code n'était requise ; en faire une aurait
introduit des régressions. La synchronisation *cohérente* (recommandée, hors
périmètre de cette consigne qui dit « de FSEG vers … ») consisterait plutôt à
remonter FSEG/FSI au niveau de Template (ajout de `AdminExitGuardFilter`,
`centralContentGuard`, `onboarding()`), et **surtout** à corriger les failles
multi-tenants ci-dessous.

---

## 2. Audit architectural

### 2.1 Incohérences

- **`settings` non scopé par site (CRITIQUE).** `SettingModel` hérite de
  `SiteScopedModel` (qui suppose une colonne `site_id`), mais la table `settings`
  **n'a pas de colonne `site_id`** (vérifié sur `ub_shared` : `site_id col: 0`).
  `SiteScopedModel::hasSiteIdColumn()` renvoie `false` → `forSite()` est un
  **no-op silencieux**. `SettingsService::all()` (`SettingsService.php:31`)
  fait donc `findAll()` **sans filtre de site** : tous les facultés lisent/écrivent
  les mêmes lignes de settings.
- **Divergence Template vs instances.** `centralContentGuard` (template) empêche la
  centrale de modifier le contenu éditorial ; FSEG/FSI en sont dépourvus → un
  superadmin central peut éditer directement le contenu d'une faculté depuis
  l'instance centrale (incohérence de responsabilité).
- **Filtre d'accès vs contexte de travail divergents** (cf. audit précédent C3/C5).
  `AdminAccessFilter::before` valide `instanceBoundSite($request)` (site lié à
  l'hôte, `AdminAccessFilter.php:29-35`) alors que le sélecteur superadmin change
  `session('active_admin_site_id')` vers un **autre** site
  (`SiteResolverService::selectAdminSite`, `:100-110`). La porte d'accès et le
  contexte édité ne coïncident pas.

### 2.2 Failles logiques

- **`FacultySiteProvisioningService.php:591`** : `$builder->get()->getRowArray()`
  sans garde. Si la requête échoue, `get()` renvoie `false` →
  `Call to a member function getRowArray() on false`. C'est la cause du
  `Échec de la création : … on false` de `site:create` et du **blocage du seeder
  `TemplateStarterSeeder`** (d'où le hang des tests DB constaté précédemment).
- **`site:create` ignore ses options CLI** (`CreateFacultySite.php:36-42`) :
  `CLI::getOption()` n'est jamais honoré, la commande appelle toujours
  `CLI::prompt()` → échoue hors TTY. La création scriptée de faculté est cassée.
- **`migrate` vs `migrate --all`.** Les migrations `App` sont appliquées, mais
  les namespaces `CodeIgniter\Shield` (`create_auth_tables`) et
  `CodeIgniter\Settings` (`CreateSettingsTable`, `AddContextColumn`) restent
  **en attente** tant qu'on n'exécute pas `migrate --all`. Sans cela, `ub_shared`
  n'a ni `settings`, ni `users`, ni `auth_*` → `healthz` 500
  (`Table 'ub_shared.settings' doesn't exist`). Vérifié et corrigé lors de l'analyse
  précédente (`php spark migrate --all` → `healthz` 204).
- **Contrat `SiteScopedModel` violé pour `settings`.** `SettingModel` déclare
  `site_id` dans `allowedFields` et le `beforeInsert` `attachActiveSiteId`
  l'injecte à l'insert — mais la colonne n'existe pas → toute création de ligne de
  settings via le modèle lève une erreur SQL (`Unknown column 'site_id'`).

### 2.3 Conflits d'intégration (central ↔ facultaire)

- **Pas de SSO** (audit précédent C1) : le lien « Administrer » du dashboard
  central (`superadmin/.../central/dashboard.php:104`) ouvre l'instance facultaire
  dans un nouvel onglet **sans session partagée** → reconnexion obligatoire.
- **Collision de nom de cookie de session** (audit précédent C2) : tous les
  dossiers utilisent `cookieName = 'ci_session'` (`app/Config/Session.php:34`).
  Les cookies sont scopés par **domaine**, pas par port → en déploiement
  same-domain (localhost en dev, ou domaine partagé), le cookie `ci_session` d'une
  instance écrase celui de l'autre.
- **Pas de synchronisation du « site courant »** : `active_admin_site_id` vit en
  session **propre à l'instance** ; changer de site dans une instance ne se reflète
  pas dans l'autre (sain pour la sécurité, mais le superadmin ne transporte pas son
  contexte dashboard → faculté).

---

## 3. Trace d'exécution simulée

### (A) Admin facultaire — `GET /admin` (tableau de bord)
1. Route `admin` → filtres `session`, `permission:admin.access`, `adminAccess`.
2. `AdminAccessFilter::before` : `isCentralAdminHost` ? non → `instanceBoundSite`
   → `canAccessFacultyAdmin(hostSite, user)` (superadmin = toujours OK ; sinon
   `admin.access` + rôle `site_admin`/`editor` dans `user_sites`).
3. `DashboardController::index` → `AdminDashboardService::data()` :
   ~14 requêtes (status posts, événements, programmes/staff publiés, messages,
   **8 × `->forSite()->orderBy()->first()`** dans `recentChanges()`).
4. Rendu `admin/dashboard.php`.
- **Erreur / bord :** aucun `active_admin_site_id` + hôte non apparié →
  `configuredOrDefaultSite` lève 404 (« site facultaire introuvable »).
- **Bord :** session expirée (glissante 7200 s, sans plafond absolu) → redirige
  `/login` ; le formulaire en cours est perdu (gardé seulement par `beforeunload`).

### (B) Superadmin — `POST /admin/site-selection`
1. `SiteController::select` : `isSuperAdmin` ? sinon `back()` + erreur.
2. `selectAdminSite($siteId)` : `canUserAccessSite` ? sinon false → erreur.
3. `session('active_admin_site_id') = $siteId` ; `settingsService->reset()` ;
   `contentTranslationService->reset()` ; redirige `/admin`.
4. Requête suivante : `adminSite()` lit `active_admin_site_id` → contexte = site choisi.
- **Bord :** `site_id` inexistant → `back()` + `error` ; l'ID de session ne change pas.
- **Bord :** éditeur/administrateur facultaire → switcher masqué (pas de `availableSites`)
  et le POST est rejeté côté contrôleur (non-superadmin).

### (C) Visiteur public — `GET /`
1. `SiteResolverService::publicSite` → `siteForRequestHost` (hôte → `sites.hostnames`).
2. Site trouvé → rendu public scopé par `site_id`.
- **Erreur :** hôte non listé en mode `requireKnownHostname=true` → 404.
- **Bord :** site `status != 'active'` → `defaultSite()`/404 selon contexte.

### (D) Superadmin — `php spark site:create --identifier fsi …`
1. `CreateFacultySite::run` : `CLI::getOption('identifier')` **renvoie null**
   (options ignorées) → `CLI::prompt()` → échoue hors TTY (ou lit stdin).
2. `createFaculty()` → `provisionStarterContent()` → `row()` ligne 591 :
   `$builder->get()->getRowArray()` sur requête en échec → **`getRowArray() on false`**
   → `CLI::error('Échec de la création …')` + `EXIT_ERROR`.
- **Conséquence : la faculté N'EST PAS créée** → on ne peut pas mettre en service
  une nouvelle faculté → **plafonne le nombre de sites exploitables**.

### (E) Admin — sauvegarde d'un réglage (`ResourceController::save`, ressource `settings`, nouvelle ligne)
1. `$model = SettingModel` ; `$model->insert($data)` (`ResourceController.php:355`).
2. `attachActiveSiteId` (beforeInsert) injecte `site_id` dans `$data`.
3. `SettingModel->allowedFields` contient `site_id` → l'INSERT inclut `site_id`
   **alors que la table `settings` n'a pas cette colonne** →
   **`SQLSTATE[42S22]: Unknown column 'site_id'` → 500**.
- Pour une ligne existante (`update`), pas d'ajout de colonne → pas d'erreur, mais
  toujours **aucun scoping** : la modification est partagée par toutes les facultés.

---

## 4. Vulnérabilités critiques & défaillances système (multi-facultés simultanés)

| # | Défaillance | Effet sur « plusieurs facultés en même temps » | Sévérité |
|---|-----------|-----------------------------------------------|----------|
| V1 | **Cookie de session `ci_session` partagé (domaine, pas port)** | Un administrateur **ne peut pas rester connecté à deux facultés simultanément** sur le même domaine : le cookie de l'une écrase l'autre → déconnexion croisée. Bloque l'administration parallelle. | HAUTE |
| V2 | **Base unique `ub_shared` (SPOF)** | Si le MySQL partagé est down/lent/bloqué, **toutes les facultés tombent ensemble**. Un verrou/transaction longue lors du provisioning d'une faculté peut geler les autres. | HAUTE |
| V3 | **`settings` global (pas de `site_id`)** | Une faculté modifiant ses coordonnées/couleurs/SEO **écrase les données des autres** ; création d'un réglage → 500 (Voir §3-E). Fuite de données inter-facultés. | HAUTE |
| V4 | **Provisioning cassé (`getRowArray on false` + `site:create` muet)** | Impossible de créer une nouvelle faculté → le nombre de sites réellement exploitables est plafonné ; scale-out impossible. | HAUTE |
| V5 | **`migrate` seul (sans `--all`)** | Instances livrées sans `settings`/`users`/`auth_*` → 500 généralisés tant que `migrate --all` n'est pas exécuté. | MOYENNE |
| V6 | **Filtre d'accès ≠ site sélectionné** (C3/C5) | Porte d'accès et contexte édité divergent ; risque latent de conflit de permission si sélection jamais accessible à un non-superadmin. | MOYENNE |
| V7 | **Pas de SSO central ↔ facultaire** (C1) | « Administrer » force une reconnexion ; friction opérationnelle (pas de panne, mais perte de productivité). | FAIBLE |
| V8 | **Verrouillage de session fichier (FileHandler)** | Au sein d'une instance, PHP `flock` le fichier de session → les requêtes concurrentes du même utilisateur se sérialisent → l'UI ralentit sous charge (pas de panne). | FAIBLE |
| V9 | **`php spark serve` mono-thread (dev-serve.sh)** | Serveur de dev intégré mono-process ; non adapté à la prod (la prod utilise un vrai serveur web, hors périmètre ici). | FAIBLE |

**Bottlenecks de performance repérés :**
- `recentChanges()` (`AdminDashboardService.php:234-335`) : **8 requêtes
  `->forSite()->orderBy()->first()`** séparées par table à chaque chargement du
  dashboard. Acceptable à un site, mais c'est du N+1 évitable (une seule requête
  `UNION`/`MAX(updated_at)` par table suffirait).
- Index `site_id` présents sur `posts/programmes/staff/pages/content_blocks/
  site_stats` (bon). `settings` n'a **aucun** index (et pas de `site_id`) →
  lecture globale full-scan dès que la table grossit.
- `centralData()` utilise des `GROUP BY site_id` (3 requêtes groupées) → **scalle
  correctement** avec le nombre de facultés.

---

## 5. Recommandations prioritaires

1. **V3 (critique) — Scoper `settings` par faculté.** Migration dédiée
   `ALTER TABLE settings ADD site_id INT UNSIGNED NOT NULL DEFAULT 0` + index, et
   vérifier que `SettingsService`/`SettingModel` écrivent bien `site_id`
   (déjà injecté par `SiteScopedModel`). Cela rend `forSite()` effectif et supprime
   la fuite inter-facultés + le 500 de création.
2. **V1 (critique) — Cookie de session par instance.** `cookieName` dérivé du
   dossier (`ci_session_fseg`, `ci_session_central`) et/ou `cookieDomain` par
   sous-domaine, + `forceGlobalSecureRequests=true` + `SameSite=Strict` en prod.
3. **V2 (critique) — Database SPOF.** Prévoir réplication/redondance MySQL et, à
   terme, l'isolation par faculté (schéma ou base dédiée) ; ajouter un
   circuit-breaker/timeout sur les connexions.
4. **V4 (critique) — Réparer le provisioning.** Garder `$builder->get()`
   (`FacultySiteProvisioningService.php:591`) et corriger la requête sous-jacente
   (colonne/manquante ou jointure) ; corriger `CreateFacultySite` pour honorer
   `CLI::getOption`. Sinon l'ajout de facultés restera impossible.
5. **V5 — Documenter `migrate --all`** comme étape obligatoire de déploiement
   (ou l'exécuter depuis `spark serve`/entrypoint).
6. **Cohérence (§1)** — Remonter FSEG/FSI au niveau de Template
   (`AdminExitGuardFilter`, `centralContentGuard`, `onboarding()`) pour aligner
   les trois modules sans régression.
7. **Perf — `recentChanges()`** : remplacer les 8 `->first()` par des requêtes
   agrégées (une par table avec `MAX(updated_at)`) pour réduire de ~14 à ~6
   requêtes le dashboard.
8. **V6 — Faire valider `AdminAccessFilter` sur le `site_id` effectif**
   (sélectionné en session pour superadmin, sinon lié à l'hôte) afin que porte
   d'accès et contexte coïncident.
