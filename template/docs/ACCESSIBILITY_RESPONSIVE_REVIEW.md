# Revue Accessibilité Et Responsive

Dernière actualisation documentaire : 24 septembre 2026.

Cette revue décrit l'état documentaire actuel et les limites à vérifier manuellement. Elle ne prétend pas remplacer un audit WCAG complet.

## Éléments Présents Dans Le Code

- Layouts public, admin et auth séparés.
- Navigation Bootstrap responsive (`navbar-expand-xl` + toggler).
- Lien d'évitement vers le contenu principal.
- Styles de focus visibles (`:focus-visible`).
- Prise en compte de `prefers-reduced-motion`.
- Libellés visibles en français pour l'authentification et l'administration.
- Sélecteur de langue public FR/EN.
- Tests unitaires dédiés dans `tests/unit/AccessibilityResponsiveTest.php` (OK — 3 tests, 38 assertions, 24 sept. 2026).
- Feuille de style unique partagée : `public/assets/css/style.css` (MD5 identique sur template + 6 instances après sync).

## Correctifs Appliqués (24 sept. 2026)

Dans [`template/public/assets/css/style.css`](../public/assets/css/style.css), puis `./scripts/sync-instances.sh` :

- `.news-card-thumb` : `min-width: 0` sous `≤767px` (évite le squeeze horizontal des cartes actualités).
- `.row.g-5` / `gx-5` / `gy-5` : gutters réduits sous `≤767px` et `≤576px` (corrige un débordement ~12 px sur téléphone).
- Topbar admin `≤576px` : actions / sélecteur de site en pleine largeur, `kbd` masqué, moins de crowding.
- Marque navbar `≤1199px` : titre / sous-titre tronqués proprement (`ellipsis`).

## Audit Navigateur (Chromium headless)

Mesure `documentElement.scrollWidth − clientWidth` aux largeurs **320 / 375 / 768 / 1024 / 1440**.

| Surface | Pages | Résultat |
|---|---|---|
| Public FSEG (`:8101`) | accueil, faculté, formations, recherche, actualités, contact, login | **0 débordement** ; toggler visible &lt; xl, masqué à 1440 |
| Admin faculté FSEG | dashboard, posts, users | **0 débordement** ; `admin-menu-toggle` visible ≤768, sidebar desktop ≥1024 ; tableaux dans `table-responsive` |
| Superadmin (`:8103`) | dashboard, users (+ site switcher) | **0 débordement** ; switcher pleine largeur sur mobile |

Total : **65 combinaisons page×largeur**, **0 overflow**. Captures locales dans `.responsive-audit/` (non versionnées).

Note outil : se connecter au superadmin via l’hôte `localhost` (pas `127.0.0.1`) pour que le cookie de session corresponde à `app.baseURL`.

## Public — Checklist

Pages couvertes par l’audit ci-dessus (détail formation / personnel / alumni : même grille Bootstrap ; non rejoués unitairement ce passage).

Contrôles :

- [x] menu mobile utilisable
- [x] pas de débordement horizontal majeur
- [x] images / cartes actualités non écrasées (thumb `min-width` corrigé)
- [ ] contrastes finaux par faculté (hors scope)
- [ ] focus clavier manuel exhaustif (hors scope de ce passage)

## Administration — Checklist

- [x] sidebar / toggle mobile
- [x] tableaux listes sans casser la page (`table-responsive`)
- [x] filtres posts empilés sous `md`
- [x] topbar + site switcher superadmin sur 320–375
- [ ] formulaires longs / messages (même layout admin ; non ciblés ce passage)

## Largeurs Testées

- 320 px
- 375 px
- 768 px
- 1024 px
- 1440 px

## Limites De La Revue Actuelle

- Audit Chromium headless uniquement (pas Safari / Firefox / appareils physiques).
- Les contrastes réels doivent être vérifiés avec les couleurs et images finales de chaque faculté.
- Tous les actifs frontaux sont auto-hébergés (`public/assets/vendor/`) : le rendu ne dépend d'aucun service externe.
- Les contenus définitifs très longs peuvent encore créer des cas non vus avec les données de démonstration.
- La navbar publique ne se replie qu’à **xl (1200 px)** : tablette paysage dense mais sans overflow mesuré.

## Critères Avant Livraison

- Aucun débordement horizontal majeur — **satisfait** sur l’échantillon audité.
- Navigation clavier possible.
- Focus visible sur liens, boutons et champs.
- Formulaires compréhensibles avec erreurs.
- Administration utilisable sur tablette et desktop — **satisfait** sur dashboard / listes auditées.
- Version mobile publique validée avec les contenus de démo — **satisfait** ; revalider avec contenus finaux.
