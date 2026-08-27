# Déploiement multi-dossiers (un dossier par site)

> **Procédure recommandée** : suivre
> [TEMPLATE_SETUP.md](TEMPLATE_SETUP.md) qui décrit la copie manuelle pas à
> pas. Ce document explique le fonctionnement et les variantes (scripts).

Ce document décrit le fonctionnement du mode multi-dossiers, complété par
la procédure pas à pas de [TEMPLATE_SETUP.md](TEMPLATE_SETUP.md). Toutes les
instances partagent exactement le même code applicatif, les mêmes migrations
et la même base de données : seule l'organisation des dossiers sur le
serveur et le contenu du `.env` de chaque dossier changent. En développement
local, chaque faculté est servie sur localhost avec son propre port
(fseg=8101, fsi=8102, superadmin=8103) via `scripts/dev-serve.sh`.

## Vue d'ensemble

```
/var/www/
├── template/       → le modèle (jamais exposé au web)
├── superadmin/     → copie du modèle, app.centralAdminMode = true
│                      Superadmin uniquement : gère les facultés, les
│                      admins facultaires et les mots de passe.
├── fseg/           → copie du modèle, app.siteSlug = fseg
│                      Site public + /admin de la FSEG.
├── fsi/            → copie du modèle, app.siteSlug = fsi
│                      Site public + /admin de la FSI.
└── droit/          → copie du modèle, app.siteSlug = droit
                       Site public + /admin de la Faculté de Droit.
```

Chaque dossier est une copie **complète et fonctionnelle** de
l'application (mêmes fichiers PHP, mêmes vues, mêmes migrations). Ce qui
distingue un dossier d'un autre, ce sont uniquement quelques lignes dans
son `.env` :

| Variable                     | Dans `superadmin/`       | Dans un dossier facultaire |
|--------------------------|-----------------------|------------------------------|
| `app.centralAdminMode`  | `true`                | `false`                     |
| `app.siteSlug`          | sans effet            | `fseg`, `fsi`, `droit`, …    |
| `database.default.*`    | **identique partout** | **identique partout**       |

**La base de données est unique et partagée par tous les dossiers.** Chaque
table de contenu (`posts`, `programmes`, `staff`, `pages`, …) porte déjà une
colonne `site_id` (voir [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md)
et [05-BASE-DE-DONNEES.md](05-BASE-DE-DONNEES.md)). C'est cette colonne, et
non le dossier, qui isole les données d'une faculté : un dossier ne fait que
dire au code "pour cette copie-là, sers uniquement les données dont
`site_id` correspond à `fsi`". C'est le même mécanisme d'isolation que le
mode par nom d'hôte ; on change seulement comment on choisit le site actif.

## Comment ça marche techniquement

- `App\Services\SiteResolverService` choisit déjà le site actif dans cet
  ordre : hôte connu → `app.siteId` → `app.siteSlug`. Un dossier facultaire
  dont le site configuré est introuvable répond 404 explicitement — jamais
  de repli vers une autre faculté. L'instance superadmin, elle, se sert du
  premier site actif pour le rendu interne.
- `App\Services\AdminAccessService::isCentralAdminInstance()` lit
  `app.centralAdminMode`. Quand c'est vrai, `isCentralAdminHost()` renvoie
  toujours `true`, quel que soit le nom d'hôte réel. Tous les contrôleurs et
  vues qui distinguaient déjà "administration centrale" vs "administration
  facultaire" par nom d'hôte (`Home`, `DashboardController`,
  `UserController`, `SiteResolverService`, `AdminAccessFilter`,
  `layouts/admin.php`) fonctionnent donc sans modification.
- `App\Filters\CentralAdminOnlyFilter` est un filtre global qui ne fait
  rien tant que `app.centralAdminMode` est faux. Quand c'est vrai, il
  redirige toute URL qui n'est pas `/admin/*` (ni une route
  d'authentification) vers `/admin`, pour garantir que le dossier `admin/`
  ne rend jamais de page publique facultaire.

Rien d'autre n'a changé dans la logique métier, les permissions Shield, la
distinction superadmin / admin facultaire / éditeur, ni les migrations.

## Créer une nouvelle faculté

Deux étapes indépendantes, dans l'ordre que vous voulez :

**1. Créer la ligne en base de données** (une seule fois, depuis n'importe
quel dossier existant, puisqu'ils partagent la même base) :

```bash
php spark site:create --identifier fsi --slug fsi --name="Faculté des Sciences et Ingénierie"
```

Cela crée le site et provisionne du contenu de démarrage éditable (page
d'accueil, hero, statistiques, un programme et un membre du personnel
d'exemple, etc. — tout est modifiable ensuite dans `/admin`). C'est
exactement ce que fait le bouton "Nouveau site" de `/admin/sites` pour un
superadmin ; la commande CLI est juste pratique pour scripter un
déploiement.

**2. Copier le dossier** pour obtenir un site fonctionnel :

```bash
./scripts/new-faculty-instance.sh fsi "Faculté des Sciences et Ingénierie" ../fsi
```

Ce script :
- copie le projet (sans `.git`, `vendor` optionnel, ni les fichiers
  temporaires de `writable/`) vers `../fsi` ;
- crée un `.env` avec `app.siteSlug = fsi` et `app.centralAdminMode = false` ;
- rappelle les deux réglages qu'il reste à faire à la main : l'URL publique
  réelle (`app.baseURL`) et les identifiants de base de données (à copier
  depuis un dossier existant — ils doivent être identiques partout).

Ensuite :
```bash
cd ../fsi
composer install --no-dev   # sauf si vous avez utilisé --link-vendor
```

C'est tout : le nouveau dossier sert immédiatement le site de la nouvelle
faculté, avec son propre `/admin` où le superadmin (ou un admin facultaire
qu'il aura assigné) peut se connecter et remplacer le contenu de démarrage.

### Option : partager `vendor/` entre dossiers

Ajoutez `--link-vendor` à la commande pour créer un lien symbolique vers le
`vendor/` du dossier source au lieu de dupliquer ~30 Mo de dépendances par
faculté. Pratique en VPS avec accès shell ; à éviter sur un hébergement
mutualisé qui ne supporte pas toujours les liens symboliques inter-dossiers
(voir [GUIDE_DEPLOIEMENT_CPANEL.md](GUIDE_DEPLOIEMENT_CPANEL.md)), auquel cas
préférez des copies complètes.

## Créer le dossier superadministration (une seule fois)

```bash
./scripts/new-admin-instance.sh ../superadmin
# puis, une fois le .env pointé vers la base partagée :
cd ../superadmin && php spark admin:create-superadmin --email vous@example.edu --username vous
```

Placez ce dossier sur son propre sous-domaine ou port dédié (en local : http://localhost:8103) et
hors du document root de chaque faculté. Ce n'est pas une exigence
technique stricte (`CentralAdminOnlyFilter` bloquerait de toute façon les
pages publiques) mais une bonne pratique : ce dossier n'a rien à faire
visible sur le domaine d'une faculté.

## Qui peut faire quoi

- **Superadmin** (compte créé via `php spark admin:create-superadmin`, actif
  dans le dossier `superadmin/` et dans tous les dossiers facultaires puisque
  les comptes vivent dans la même base) :
  - crée/désactive des facultés (`/admin/sites` depuis `superadmin/`) ;
  - crée les comptes admin facultaire et fixe/réinitialise leur mot de
    passe (`/admin/users` depuis `superadmin/`) ;
  - garde aussi un accès complet à l'administration de chaque faculté.
- **Admin facultaire** (groupe Shield `admin`, assigné à un site via
  `user_sites`) : se connecte directement sur `/admin` **du dossier de sa
  faculté**, et peut y créer/gérer ses propres éditeurs
  (`/admin/users`, restreint aux utilisateurs de son site — logique déjà
  présente dans `AdminAccessService::canManageFacultyUsers()`).
- **Éditeur** : se connecte sur `/admin` du dossier de sa faculté, gère le
  contenu selon ses permissions (actualités, programmes, personnel, etc.),
  ne gère pas les utilisateurs.

Rien de nouveau ici par rapport au modèle par nom d'hôte : c'est la même
logique de rôles et de permissions, seulement accédée via un dossier dédié
plutôt qu'un sous-domaine partagé.

## Maintenir plusieurs copies à jour

C'est le principal compromis du mode multi-dossiers : un correctif de code
doit être répété dans chaque dossier (contrairement au mode par nom
d'hôte, où une seule instance sert tout le monde). Deux approches :

- **Petit nombre de facultés / hébergement simple** : gardez ce dossier
  comme "modèle" versionné (Git), et pour chaque mise à jour, régénérez
  chaque dossier facultaire avec le script (il préserve leur `.env`
  puisqu'il ne l'écrase pas s'il existe déjà — sauvegardez-le avant, ou
  fusionnez les deux `.env` à la main) puis relancez les migrations sur la
  base partagée (une fois suffit, la base est unique).
- **VPS avec accès shell** : utilisez `--link-vendor` et un script de
  déploiement (`git pull` + `rsync` du code applicatif vers chaque dossier,
  en excluant `.env` et `writable/`) pour propager les changements de code
  en une seule commande.

Dans les deux cas, les migrations (`php spark migrate`) ne se lancent
qu'une fois : la base est commune.

## Documents liés

- [ARCHITECTURE_MULTI_SITES.md](ARCHITECTURE_MULTI_SITES.md) : le modèle de
  données `sites` / `user_sites` / `site_id`, commun aux deux modes de
  déploiement.
- [GUIDE_DEPLOIEMENT_CPANEL.md](GUIDE_DEPLOIEMENT_CPANEL.md) : contraintes
  d'un hébergement mutualisé (utile si vous hébergez chaque dossier sur un
  domaine addon cPanel).
