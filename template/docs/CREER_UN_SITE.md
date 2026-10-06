# Créer un site facultaire

Ce document reste dans `template/` : il n’est pas recopié dans les dossiers
des facultés ni de la superadministration.

Pour ouvrir une nouvelle faculté, on copie le modèle, on renseigne quelques
réglages, puis on lance trois commandes. Aucune connaissance en
programmation n’est nécessaire au-delà de copier un dossier et d’ouvrir un
terminal. Pour préparer aussi le **contenu** (textes, images, formations)
d’une nouvelle faculté, suivre en plus
[AGENT_NOUVEAU_SITE.md](AGENT_NOUVEAU_SITE.md).

Toutes les facultés et la superadministration partagent **la même base de
données** ; chaque site ne voit que ses contenus (`site_id`).

## Ce dont vous avez besoin

- une copie du dossier `template/` ;
- l’adresse publique du futur site (exemple : `https://fseg.ub.edu.bi/`) ;
- les identifiants de la base déjà utilisée par les autres facultés ;
- un identifiant court en minuscules, sans espace (exemple : `fseg`).

PHP, Composer et le serveur web sont décrits dans
[DEPLOIEMENT.md](DEPLOIEMENT.md).

## 1. Copier le dossier

Depuis le dossier parent de `template/` :

```bash
cp -r template/ fseg
rm -f fseg/.env
rm -f fseg/docs/CREER_UN_SITE.md fseg/docs/AGENT_NOUVEAU_SITE.md
rm -rf fseg/docs/archive fseg/demo-media
```

Remplacez `fseg` par l’identifiant de la faculté. Ne copiez jamais le
`.env` d’un autre site : il contient des secrets. Le script
`template/scripts/new-faculty-instance.sh` fait la même copie et refuse de
partager `vendor/`.

Ajoutez ensuite l’identifiant dans la liste de
`template/scripts/sync-instances.sh` (boucle `for dest_name in …`), pour que
les prochaines mises à jour du code atteignent ce dossier.

## 2. Remplir le `.env`

```bash
cd fseg
cp .env.example .env
php spark key:generate
composer install
```

Ce qui distingue un dossier d’un autre tient en quelques lignes :

| Clé | Dossier facultaire (ex. `fseg`) | Dossier `superadmin/` |
|---|---|---|
| `app.baseURL` | `https://fseg.ub.edu.bi/` (avec `/` final) | adresse de la superadministration |
| `database.default.*` | **identique partout** | **identique partout** |
| `app.centralAdminMode` | `false` | `true` |
| `app.siteSlug` | `fseg` | sans effet |
| `app.publicHostPattern` | `{slug}.ub.edu.bi` (vide en local) | sans effet |
| `session.cookieName` | `ci_session_fseg` | `ci_session_central` |
| `session.rememberCookieName` | `remember_fseg` | `remember_central` |
| `app.uploadMirrors` | vide | `fseg:/var/www/fseg,fsi:/var/www/fsi,…` |
| `app.previewBase.{slug}` | inutile | adresse d’aperçu de chaque faculté |
| `app.proxyIPs` | IP du reverse proxy, ou vide | idem |

Les cookies propres à chaque dossier évitent que les connexions se
mélangent sur le même ordinateur. `X-Forwarded-Host` n’est lu que si
`app.proxyIPs` est renseigné.

## 3. Trois commandes

Les migrations ne se lancent **qu’une fois** pour toute la plateforme
(depuis n’importe quel dossier déjà installé) :

```bash
php spark migrate --all
```

Dans le **nouveau** dossier, créez la faculté dans la base :

```bash
php spark site:create --identifier fseg --slug fseg \
  --name="Faculté des Sciences Économiques et de Gestion"
```

`--hostname fseg.ub.edu.bi` fixe l’hôte public ; sinon il est déduit de
`app.publicHostPattern`. Le site démarre avec des textes d’exemple « à
remplacer » : le tableau de bord `/admin` liste ce qu’il reste à compléter.

Puis le premier administrateur de la faculté, **depuis ce dossier** :

```bash
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
  --email admin-faculte@example.edu --username adminfac \
  --password-env PLATFORM_ADMIN_PASSWORD
```

Le compte reçoit le groupe `admin` pour cette seule faculté. Un
superadministrateur peut aussi le créer depuis **Comptes** dans la
superadministration. L’administrateur crée ensuite ses éditeurs (ou
d’autres administrateurs) depuis le `/admin` de sa faculté.

## 4. Vérifier

- site public : l’adresse de `app.baseURL` ;
- administration : la même adresse suivie de `/admin` ;
- santé : `/healthz` (réponse 204).

Le personnel de la faculté n’administre que **ce** dossier. Le
superadministrateur se connecte sur `superadmin/`, choisit la faculté et
modifie son contenu sans quitter cette application.

## Dossier de superadministration (une seule fois)

```bash
cd template && ./scripts/new-admin-instance.sh ../superadmin
cd ../superadmin
php spark admin:create-superadmin --email vous@example.edu --username vous \
  --password-env PLATFORM_ADMIN_PASSWORD
```

`admin:create-superadmin` ne fonctionne que si `app.centralAdminMode=true`.
C’est le **seul** moyen de créer un superadministrateur : l’écran Comptes
ne le permet pas. Placez ce dossier hors du document root des facultés.

## Fonctionnement des copies

- `SiteResolverService` choisit le site : hôte connu, puis `app.siteId`,
  puis `app.siteSlug`. Un dossier facultaire dont le site est introuvable
  répond 404, jamais avec une autre faculté.
- `app.centralAdminMode=true` transforme une copie en superadministration :
  toute adresse hors `/admin` et authentification redirige vers `/admin`.
- Les fichiers téléversés depuis la superadministration sont enregistrés
  dans `public/uploads/sites/{slug}/` de cette instance puis recopiés vers
  le dossier de la faculté (`app.uploadMirrors`, sinon `../{slug}/public`
  s’il contient `index.php`).
- Chaque instance a son propre `vendor/` : un lien symbolique ferait
  charger le code du modèle à la place de celui du dossier.

## Maintenir les copies

Un correctif se fait dans `template/`, puis :

```bash
cd template && ./scripts/sync-instances.sh
```

Le script recopie `app/`, `tests/` et `public/assets/` (avec suppression des
fichiers retirés), la documentation d’exploitation (`README.md`,
`AGENTS.md`, `CHANGELOG.md` et une partie de `docs/`), et supprime
`public/robots.txt`. Il ne touche jamais aux `.env`, à `writable/`, à
`vendor/` ni à `public/uploads/`. Après un changement de schéma, relancer
`php spark migrate --all` **une fois** sur la base partagée.

## Contenu de démonstration

Pour remplir une faculté comme un site terminé (en local ou en recette),
voir [OPERATIONS_LOCALES.md](OPERATIONS_LOCALES.md) : `php spark
site:seed-demo` depuis `superadmin/`, puis `php scripts/update-faculty-maps.php`
à la racine de la plateforme.
