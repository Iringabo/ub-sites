# Créer un site facultaire

Ce dossier est le **modèle**. Pour ouvrir une nouvelle faculté, on le copie,
on renseigne trois réglages, puis on lance deux commandes. Aucune
connaissance en programmation n’est nécessaire au-delà de copier un dossier
et d’ouvrir un terminal.

Toutes les facultés et la superadministration partagent **la même base de
données**. Chaque site ne voit que ses propres contenus (`site_id`).

## Ce dont vous avez besoin

- une copie de ce dossier `template/`
- l’adresse publique du futur site (exemple : `https://fseg.ub.edu.bi/`)
- les identifiants de la base déjà utilisée par les autres facultés
- un identifiant court en minuscules, sans espaces (exemple : `fseg`)

PHP, Composer et le serveur web sont décrits en [annexe](#annexe-technique).

## 1. Copier le dossier

Placez-vous dans le dossier parent de `template/`, puis :

```bash
cp -r template/ fseg
rm -f fseg/.env
```

Remplacez `fseg` par l’identifiant de la faculté. Ne copiez jamais le fichier
`.env` d’un autre site : il contient des secrets.

Un script optionnel fait la même copie : `scripts/new-faculty-instance.sh`.

Après création, pour charger le contenu de démonstration UB (local) ou
mettre à jour les cartes : voir [LOCAL_OPERATIONS.md](LOCAL_OPERATIONS.md)
et les scripts à la racine de la plateforme.

## 2. Remplir le `.env` (réglages essentiels)

```bash
cd fseg
cp .env.example .env
```

Ouvrez `.env` et renseignez au minimum :

| Champ | Exemple | Rôle |
|---|---|---|
| `app.baseURL` | `https://fseg.ub.edu.bi/` | Adresse publique du site (avec le `/` final) |
| `database.default.*` | les **mêmes** valeurs que les autres facultés | Base partagée |
| `app.siteSlug` | `fseg` | Identifiant de cette faculté |
| `app.publicHostPattern` | `{slug}.ub.edu.bi` | Hôte public écrit par `site:create`. `{slug}` est remplacé. Laisser vide en local. |

Pour une faculté, laissez `app.centralAdminMode = false`.

Ajoutez aussi un nom de cookie propre à ce dossier, pour que les connexions
ne se mélangent pas sur le même ordinateur :

```
session.cookieName = ci_session_fseg
session.rememberCookieName = remember_fseg
```

### Checklist `.env` par instance

Les mêmes clés isolent les sessions, les téléversements et les proxys. À recopier dans chaque dossier :

| Clé | Faculté (ex. `fseg`) | Superadministration |
| --- | --- | --- |
| `session.cookieName` | `ci_session_fseg` | `ci_session_central` |
| `session.rememberCookieName` | `remember_fseg` | `remember_central` |
| `app.uploadMirrors` | laisser vide (copie sœur `../{slug}/public` si présent) | `fseg:/chemin/fseg,fsi:/chemin/fsi` |
| `app.previewBase.{slug}` | inutile | ex. `http://localhost:8101/` pour l’aperçu local |
| `app.proxyIPs` | IPs du reverse proxy (liste à virgules), ou vide sans proxy | idem |

`X-Forwarded-Host` n’est lu que lorsque `app.proxyIPs` est renseigné.

Puis générez la clé de chiffrement :

```bash
php spark key:generate
```

## 3. Deux commandes

Les migrations de la base ne se lancent **qu’une fois** pour toute la
plateforme (depuis n’importe quel dossier déjà installé) :

```bash
php spark migrate --all
```

Dans le **nouveau** dossier, créez la faculté dans la base :

```bash
php spark site:create --identifier fseg --slug fseg --name="Faculté des Sciences Économiques"
```

Adaptez l’identifiant, le slug et le nom. Le site démarre avec des textes
d’exemple (« à remplacer ») : le tableau de bord `/admin` indique ensuite
quoi compléter.

## 4. Premier compte

- **Administrateur de la faculté** (depuis le dossier de la faculté) :

```bash
PLATFORM_ADMIN_PASSWORD='…' php spark admin:create-faculty-admin \
  --email admin-faculte@example.edu --username adminfac \
  --password-env PLATFORM_ADMIN_PASSWORD
```

  Le compte reçoit le groupe `admin` et le rôle `site_admin` **uniquement**
  pour le `app.siteSlug` de ce dossier. Alternative : un superadministrateur
  le crée depuis **Comptes** dans la superadministration.
- **Éditeurs** : l’administrateur de la faculté les crée ensuite depuis
  `/admin` de *son* site.

Si la superadministration n’existe pas encore, copiez aussi `template/` vers
`superadmin/`, mettez `app.centralAdminMode = true`,
`session.cookieName = ci_session_central`, puis **uniquement dans ce dossier** :

```bash
php spark admin:create-superadmin --email admin@example.edu --username admin
```

`admin:create-superadmin` est refusé dans un dossier facultaire
(`app.centralAdminMode=false`). Un superadministrateur déjà connecté peut
aussi créer d’autres superadmins via l’UI Comptes.
## Vérifier

- Site public : l’adresse indiquée dans `app.baseURL`
- Administration : la même adresse, avec `/admin`
- Santé : `/healthz` (réponse 204)

Le personnel de la faculté n’administre que *ce* dossier. Le superadministrateur
se connecte sur le dossier `superadmin/`, choisit une faculté, et modifie son
contenu sans quitter cette application.

Les fichiers téléversés depuis une instance (souvent la superadministration)
sont enregistrés dans `public/uploads/sites/{slug}/` de **cette** instance,
puis copiés vers le dossier public de la faculté si ce n’est pas le même
chemin. Sur l’instance centrale, indiquez la carte des dossiers :

```
app.uploadMirrors = fseg:/var/www/fseg,fsi:/var/www/fsi
```

Chaque chemin peut pointer vers le dossier de l’instance (celui qui contient
`public/`) ou directement vers `public/`. Sans cette carte, la copie utilise
`../{slug}/public` s’il contient `index.php`. Les slugs `template` et `demo`
ne sont jamais recopiés. Une faculté qui téléverse son propre slug ne copie
rien (même dossier).

## Annexe technique

### Composer

Dans chaque copie :

```bash
composer install --no-dev
```

### Nginx (document root = `public/` uniquement)

```nginx
server {
    listen 443 ssl;
    server_name fseg.ub.edu.bi;
    root /var/www/fseg/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    }
}
```

Le fichier `deploy/nginx/uploads-security.conf` interdit l’exécution de
scripts sous `/uploads/`. L’équivalent Apache est dans
`public/uploads/.htaccess`.

### Production

```bash
php spark app:production-check
```

Cette commande vérifie notamment que la table `settings` porte bien la
colonne `site_id` (réglages d’identité par faculté).
