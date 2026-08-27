# Architecture Actuelle

Dernière actualisation : 25 août 2026. Ce document décrit l'état présent du
dépôt et de la plateforme déployée ; les documents historiques éventuels
portent un bandeau explicite.

## Vue D'Ensemble

Le dépôt est le **modèle (template)** d'une plateforme multi-facultés :
une application CodeIgniter 4 que l'on copie pour créer chaque instance.

```
<racine plateforme>/
├── template/      ← ce dépôt (jamais exposé au web)
├── fseg/          ← copie : app.siteSlug=fseg   → site public + /admin FSEG
├── fsi/           ← copie : app.siteSlug=fsi    → site public + /admin FSI
└── superadmin/    ← copie : app.centralAdminMode=true → pilotage global
```

- **Une seule base MySQL/MariaDB** partagée par toutes les instances ;
  l'isolation des contenus repose sur `site_id` (jamais sur le dossier).
- Les migrations se jouent **une fois**, depuis n'importe quelle instance.
- Chaque instance possède son propre `vendor/` (autoload régénéré) et son
  propre `.env` (jamais recopié d'une instance à l'autre).

## Instances Et Développement Local

| Instance | URL locale | `.env` clé |
|---|---|---|
| fseg | http://localhost:**8101** | `app.siteSlug=fseg` |
| fsi | http://localhost:**8102** | `app.siteSlug=fsi` |
| superadmin | http://localhost:**8103** | `app.centralAdminMode=true` |

```bash
cd fseg && ./scripts/dev-serve.sh start    # démarre les trois instances
./scripts/dev-serve.sh status              # healthz par instance
./scripts/dev-serve.sh stop                # arrêt
# logs : /tmp/platform-dev-<instance>.log
```

Les ports sont lus dans le `app.baseURL` de chaque `.env`
(`PLATFORM_INSTANCES` permet de surcharger la liste des instances).

## Résolution Du Site Actif

Ordre appliqué par `App\Services\SiteResolverService` :

1. Requête `/admin/*` : site sélectionné en session (superadmin uniquement),
   sinon site lié au dossier ;
2. Hôte de la requête listé dans les domaines (`hostnames`) d'un site actif ;
3. `app.siteId` puis `app.siteSlug` ;
4. **Dossier facultaire (HTTP)** : si le site configuré est introuvable ou
   inactif → **404 française explicite** (jamais de repli vers une autre
   faculté) ;
5. Instance superadmin / CLI : premier site actif, sinon entité neutre
   « Site à configurer ».

## Modèle D'Accès

Groupes Shield : `superadmin`, `admin`, `editor`
(matrice complète dans `app/Config/AuthGroups.php`).

| Qui | Où | Peut faire |
|---|---|---|
| Superadministrateur | dossier `superadmin/` (connexion dédiée) | tout : sites, utilisateurs, mots de passe, contenu de chaque faculté |
| Administrateur de faculté (`admin` + `user_sites.role=site_admin`) | **uniquement** `/admin` de son dossier | contenu de sa faculté, messages, création d'éditeurs |
| Éditeur | `/admin` de son dossier | contenu selon permissions ; pas d'utilisateurs |

Séparation stricte garantie par `AdminAccessFilter` (site « lié » au dossier
via `instanceBoundSite()`) : le personnel d'une autre faculté est renvoyé à
l'accueil avec erreur, même sur un hôte non déclaré. Le sélecteur de site est
réservé au superadmin (masqué côté UI, refusé côté serveur). Un dossier
facultaire mal configuré ne peut donc **jamais** servir le contenu d'un autre
site.

## Isolation Multi-Sites

- Tables `sites` et `user_sites` ; `site_id` sur tous les contenus,
  paramètres, messages et traductions.
- Lectures/écritures via `SiteScopedModel::forSite()` ; index uniques
  **par site** (slugs, clés, codes…).
- Médias publics rangés sous `public/uploads/sites/{slug}/…`.
- Création d'une faculté = provisionnement complet d'un contenu de départ
  neutre (« Texte à remplacer ») : bouton « Nouveau site » du superadmin ou
  `php spark site:create --identifier <id> --slug <slug> --name "…"`.
  Procédure complète : [TEMPLATE_SETUP.md](TEMPLATE_SETUP.md).

## Front-End Auto-Hébergé

Bootstrap 5.3.3, Bootstrap Icons 1.11.3 et la police Inter vivent dans
`public/assets/vendor/` : **aucune dépendance CDN** ; la plateforme fonctionne
hors ligne. La CSP n'autorise que `'self'` (scripts/styles/polices), avec
seule exception fonctionnelle `frame-src https://www.google.com` pour la carte
optionnelle de la page contact.

## Bilinguisme

Français langue source et défaut ; anglais public optionnel via
`content_translations` (fallback français). Cookie de langue :
`site_locale`. Administration, authentification et erreurs back-office :
français exclusivement.

## Sécurité (synthèse)

Shield (groupes/permissions), CSRF global, secure headers, CSP stricte,
validation serveur systématique, uploads contrôlés (extension + MIME réels +
`getimagesize`, noms aléatoires, garde anti-traversée), limitations de débit
du formulaire de contact par e-mail (120 s) **et** par IP (20/h),
verrous anti-auto-lockout et dernier-superadmin. Détails :
[SECURITY_REVIEW.md](SECURITY_REVIEW.md) et
[AUDIT_2026-08-25.md](AUDIT_2026-08-25.md).

## Qualité

- Suite PHPUnit : **21 fichiers, verts** (unitaires, base de données,
  feature, session) ; base dédiée `platform_test` supprimée/recréée à
  chaque exécution (`php spark test`, lancer les classes séquentiellement).
- `composer validate`, `php spark routes`, `php -l` : propres.
- Dernier audit complet : [AUDIT_2026-08-25.md](AUDIT_2026-08-25.md).

## Commandes Utiles

```bash
composer validate && php spark routes && php spark migrate:status
php spark test                                   # base platform_test (destructif)
php spark site:create --identifier droit --slug droit --name "Faculté de Droit"
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-superadmin \
    --email admin@example.edu --username admin
php spark app:production-check [--strict]
```

## Documents Liés

- [TEMPLATE_SETUP.md](TEMPLATE_SETUP.md) : créer une instance, pas à pas.
- [MULTI_FOLDER_DEPLOYMENT.md](MULTI_FOLDER_DEPLOYMENT.md) : fonctionnement du
  mode multi-dossiers.
- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md) : modèle de
  données `sites` / `user_sites` / `site_id`.
- [05-BASE-DE-DONNEES.md](05-BASE-DE-DONNEES.md) : schéma issu des migrations.
