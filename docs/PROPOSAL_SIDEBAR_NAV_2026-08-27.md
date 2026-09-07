# Proposition — Nouvelle navigation latérale (sidebar) FSEG admin

Date : 2026-08-27
Réf. code : `app/Views/layouts/admin.php:24-85` (navGroups actuels),
`app/Config/Routes.php` (segments), `app/Config/AuthGroups.php:90-130` (matrice
de permissions), `app/Services/AdminAccessService.php` (rôles `site_admin`/`editor`).

Objectif : regrouper les éléments de la sidebar par **tâche administrative** et
faire apparaître/distribuer les entrées en fonction du **rôle réel** (Éditeur vs
Administrateur vs Superadmin), afin de réduire la charge cognitive et
l'écart entre le périmètre affiché et le périmètre autorisé.

---

## 0. État actuel (problème)

La sidebar actuelle (`admin.php:24-85`) définit **8 groupes figés** dans la vue :
`Accueil, Contenus, Faculté, Messagerie, Sécurité, Recherche, Communauté,
Configuration`. Les liens sont filtrés par permission via `$canUseLink`, mais :

- la **structure** des groupes est identique pour tous les rôles (un éditeur
  voit les mêmes 8 en-têtes qu'un superadmin) ;
- le groupe `Configuration` (settings + sites) n'apparaît **que** pour le
  superadmin, créant un groupe « fantôme » vide pour les autres ;
- aucune distinction visuelle n'indique ce qu'un **Éditeur** *ne peut pas*
  modifier (Messagerie, Utilisateurs, Paramètres, Sites).

## 1. Matrice de visibilité par rôle (source de vérité)

Déduite de `AuthGroups.php` (matrice) + segments de route (`Routes.php`) :

| Élément (segment route) | Permission requise | Éditeur | Administrateur | Superadmin |
|---|---|:--:|:--:|:--:|
| Tableau de bord (`admin`) | `admin.access` | ✅ | ✅ | ✅ |
| Contenu accueil (`home-content/-hero-slides/-highlights/-stats`) | `home.manage` | ✅ | ✅ | ✅ |
| Actualités & Événements (`posts`) | `news.manage`,`events.manage` | ✅ | ✅ | ✅ |
| Formations (`programmes`) | `programmes.manage` | ✅ | ✅ | ✅ |
| Pages / Blocs / Historique (`pages`,`content-blocks`,`timeline-items`) | `pages.manage` | ✅ | ✅ | ✅ |
| Présentation faculté (`faculty/profile`) | `pages.manage` | ✅ | ✅ | ✅ |
| Personnel (`staff`) | `staff.manage` | ✅ | ✅ | ✅ |
| Alumni / Témoignages (`alumni-profiles`,`testimonials`) | `alumni.manage` | ✅ | ✅ | ✅ |
| Recherche (`laboratories`,`publications`,`research-projects`) | `research.manage` | ✅ | ✅ | ✅ |
| **Messages de contact (`messages`)** | `messages.manage` | ❌ | ✅ | ✅ |
| **Utilisateurs (`users`)** | `users.manage` | ❌ | ✅ | ✅ |
| **Paramètres (`settings`)** | `settings.manage` | ❌ | ❌ | ✅ |
| **Sites facultaires (`sites`)** | `sites.manage` | ❌ | ❌ | ✅ |

> Différentiel clé : **Éditeur → Administrateur** = + Messagerie + Utilisateurs.
> **Administrateur → Superadmin** = + Paramètres + Sites facultaires.

---

## 2. Nouvelle hiérarchie proposée (3 zones de travail + dashboard)

Regroupement par **tâche**, non par découpage technique (supprime « Faculté » et
« Sécurité » comme groupes isolés, réintègre-les dans des zones cohérentes) :

```
DASHBOARD (toujours)
└─ Tableau de bord

ZONE 1 — CONTENU & COMMUNICATION        (home / news / events / programmes / pages)
   ├─ Page d'accueil          [home.manage]      → home-content, hero-slides, highlights, stats
   ├─ Actualités & Événements [news.manage, events.manage] → posts
   ├─ Formations              [programmes.manage] → programmes
   ├─ Pages institutionnelles [pages.manage]     → pages, content-blocks, timeline-items
   └─ Présentation faculté    [pages.manage]     → faculty/profile

ZONE 2 — COMMUNAUTÉ & RECHERCHE         (staff / alumni / research)
   ├─ Personnel               [staff.manage]      → staff
   ├─ Alumni & Témoignages   [alumni.manage]     → alumni-profiles, testimonials
   └─ Recherche              [research.manage]    → laboratories, publications, research-projects

ZONE 3 — MESSAGERIE & SÉCURITÉ          (messages / users / settings / sites)
   ├─ Messages de contact     [messages.manage]   → messages        (Admin + Superadmin)
   ├─ Utilisateurs            [users.manage]      → users          (Admin + Superadmin)
   ├─ Paramètres             [settings.manage]    → settings       (Superadmin)
   └─ Sites facultaires       [sites.manage]      → sites          (Superadmin)
```

**Règles d'affichage :**
- Une **zone entière** est masquée si l'utilisateur ne détient *aucune* des
  permissions de ses enfants (ex. l'Éditeur ne voit jamais la Zone 3).
- À l'intérieur d'une zone, seuls les liens dont la permission est possédée
  sont rendus (logique `$canUseLink` conservée).
- Le groupe « Page d'accueil » reste repliable (4 sous-pages partagent
  `home.manage`) ; tous les autres sont des liens directs pour limiter
  l'imbrication (profondeur ≤ 2).

---

## 3. Mockups descriptifs (rendu par rôle)

### 3.1 — ÉDITEUR (`editor`)
```
┌─────────────────────────────────────┐
│  Administration FSEG · UB            │  brand (site courant)
├─────────────────────────────────────┤
│  🏠 Tableau de bord                  │
│                                       │
│  CONTENU & COMMUNICATION              │
│    🏠 Page d'accueil  ▸               │
│    📰 Actualités & Événements         │
│    🎓 Formations                      │
│    📄 Pages institutionnelles         │
│    🏛  Présentation faculté           │
│                                       │
│  COMMUNAUTÉ & RECHERCHE               │
│    👤 Personnel                       │
│    🏆 Alumni & Témoignages            │
│    🔬 Recherche                       │
│                                       │
│  [Voir le site]      [Déconnexion]    │
└─────────────────────────────────────┘
  → ZONE 3 absente (pas messages/utilisateurs/params/sites)
```

### 3.2 — ADMINISTRATEUR (`admin`)
```
┌─────────────────────────────────────┐
│  Administration FSEG · UB            │
├─────────────────────────────────────┤
│  🏠 Tableau de bord                  │
│  CONTENU & COMMUNICATION  (identique Éditeur)
│  COMMUNAUTÉ & RECHERCHE   (identique Éditeur)
│                                       │
│  MESSAGERIE & SÉCURITÉ                │  ← zone ajoutée
│    ✉  Messages de contact    (badge) │
│    👥 Utilisateurs                    │
│                                       │
│  [Voir le site]      [Déconnexion]    │
└─────────────────────────────────────┘
  → Paramètres / Sites toujours masqués (réservés superadmin)
```

### 3.3 — SUPERADMIN (`superadmin`, instance centrale)
```
┌─────────────────────────────────────┐
│  Administration UB · Plateforme       │  brand central
│  [ Sélecteur de site ▾ ]             │  switcher (si >1 site)
├─────────────────────────────────────┤
│  🏠 Tableau de bord                  │
│  CONTENU & COMMUNICATION  (complet)   │
│  COMMUNAUTÉ & RECHERCHE   (complet)   │
│  MESSAGERIE & SÉCURITÉ                │
│    ✉  Messages de contact            │
│    👥 Utilisateurs                    │
│    ⚙  Paramètres           (nouveau) │
│    🗺  Sites facultaires    (nouveau) │
│                                       │
│  [Voir le site]      [Déconnexion]    │
└─────────────────────────────────────┘
```

---

## 4. Bénéfices attendus

- **Éditeur** : plus de 8 en-têtes génériques ni de groupe « Configuration »
  vide ; il voit uniquement ce qu'il a le droit de modifier → charge cognitive
  réduite, onboarding clarifié (cf. audit UX précédent).
- **Administrateur** : distingue nettement sa responsabilité « communication +
  messagerie + utilisateurs » sans être exposé à des réglages d'instance qui ne
  le concernent pas.
- **Superadmin** : retrouve Paramètres/Sites dans la même zone logique que
  Utilisateurs, cohérente entre l'instance facultaire et le dashboard central.

## 5. Note d'implémentation (pour revue)

- Externaliser la structure dans un `AdminNavigationService` (déjà recommandé
  dans l'audit UX) plutôt que dans `admin.php`. Chaque entrée porte
  `{ label, icon, route, permission(s), group }` ; la vue rend génériquement,
  la logique `$canUseLink` (`admin.php:87-95`) étant conservée telle quelle.
- Masquer une **zone** si aucun enfant n'est visible (évite les groupes
  fantômes comme « Configuration » aujourd'hui).
- Ajouter un **badge de tâches** sur « Messages de contact » (compteur de non
  lus) — utile pour guider l'attention de l'Administrateur.
- Respecter `aria-current` et `collapse` Bootstrap déjà en place
  (`admin.php:98-151`) ; la Zone 3 reste repliable sur mobile.
