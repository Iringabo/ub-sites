# Mise En Place Du Modèle (Copie Manuelle)

Ce document décrit comment transformer une copie manuelle du modèle en
instance fonctionnelle, **sans script**. Deux types d'instances existent :

| Instance | Contenu | `.env` clé |
|---|---|---|
| **Faculté** | site public + `/admin` de cette seule faculté | `app.siteSlug = <slug>` |
| **Superadministration** | connexion + tableau de bord sur toutes les facultés | `app.centralAdminMode = true` |

Toutes les instances partagent **la même base de données** ; l'isolation des
contenus repose sur `site_id`. Les migrations ne se lancent qu'**une fois**.

## 0. Prérequis

- PHP 8.2+ (extensions : `intl`, `mbstring`, `mysqli`, `fileinfo`, `dom`, `curl`)
- Composer
- MySQL/MariaDB avec une base vide créée (ex. `plateforme`) et un utilisateur associé

## 1. Copier le modèle

Depuis le parent du dossier modèle :

```bash
# Une instance facultaire :
cp -r template/ fseg/
rm -f fseg/.env
rm -rf fseg/vendor fseg/writable/logs/* fseg/writable/debugbar/* \
       fseg/writable/session/* fseg/writable/cache/* fseg/build/*

# L'instance superadministration :
cp -r template/ superadmin/
# ... mêmes nettoyages que ci-dessus
```

À exclure systématiquement d'une copie : `.env` (secret par instance),
`vendor/` (régénéré à l'étape 2), contenus d'exécution de `writable/`,
données présentes dans `public/uploads/`.

> Sur un hébergement mutualisé sans accès shell, uploadez une archive du
> modèle puis suivez les mêmes étapes via le gestionnaire de fichiers ;
> voir [GUIDE_DEPLOIEMENT_CPANEL.md](GUIDE_DEPLOIEMENT_CPANEL.md).

## 2. Installer les dépendances

Dans chaque copie :

```bash
cd fseg && composer install --no-dev
```

## 3. Configurer le `.env`

```bash
cp .env.example .env
php spark key:generate        # remplit encryption.key
```

Clés à ajuster **dans chaque instance** :

| Clé | Faculté (`fseg/`) | Superadmin (`superadmin/`) |
|---|---|---|
| `CI_ENVIRONMENT` | `production` en exploitation | idem |
| `app.baseURL` | `https://fseg.exemple.edu/` | `https://admin.exemple.edu/` |
| `app.forceGlobalSecureRequests` | `true` (HTTPS) | `true` |
| `app.siteSlug` | `fseg` | vide ou slug par défaut |
| `app.centralAdminMode` | `false` | **`true`** |
| `app.requireKnownHostname` | `true` recommandé | sans objet |
| `database.default.*` | **identiques partout** | **identiques partout** |
| `CONTACT_NOTIFICATION_*` | SMTP de la faculté | sans objet |

Ne jamais recopier le `.env` d'une instance vers une autre.

## 4. Créer la base et lancer les migrations (une seule fois)

```bash
cd fseg                 # n'importe quelle instance, la base est unique
php spark migrate --all
php spark migrate:status   # tout doit être « up »
```

## 5. Créer le premier site facultaire

Depuis n'importe quelle instance :

```bash
php spark site:create --identifier fseg --slug fseg --name="Faculté de démonstration"
```

Le provisionneur génère le contenu de départ neutre (« Texte à remplacer »),
modifiable ensuite dans `/admin`. Un site peut aussi être créé depuis
l'interface superadmin (`/admin/sites` → « Nouveau site »), en renseignant
les domaines publics du site (champ `hostnames`, un par ligne).

Répéter pour chaque faculté hébergée.

## 6. Créer le compte superadministrateur (une seule fois)

```bash
cd ../superadmin
PLATFORM_ADMIN_PASSWORD='MotDePasseFort!' php spark admin:create-superadmin \
    --email admin@example.edu --username admin
```

Ce compte existe dans la base partagée : il permet de se connecter au
tableau de bord central **et** à l'`/admin` de chaque faculté.

## 7. Créer les comptes du personnel facultaire

Se connecter sur `/admin` de l'instance superadmin :

- `/admin/users` → créer l'administrateur de la faculté (groupe `admin`,
  affecté au site concerné) ;
- cet administrateur crée ensuite ses propres éditeurs depuis le `/admin`
  de **sa** faculté.

Séparation stricte : le personnel d'une faculté ne peut administrer que via
le dossier de sa faculté ; toute autre tentative est refusée.

## 8. Serveur web

Le document root de chaque instance doit pointer vers son dossier `public/`
seulement. Exemple Nginx (répéter par instance) :

```nginx
server {
    listen 443 ssl;
    server_name fseg.exemple.edu;
    root /var/www/fseg/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    }
    include /var/www/template/deploy/nginx/uploads-security.conf; # si partagé
}
```

Le fichier `deploy/nginx/uploads-security.conf` du modèle interdit
l'exécution de scripts sous `/uploads/` ; l'équivalent Apache est déjà
présent dans `public/uploads/.htaccess`.

## 9. Vérifications finales

```bash
php spark app:production-check          # dans chaque instance
curl -s https://fseg.exemple.edu/healthz # 204 attendu
```

- Le site public affiche le contenu de départ neutre.
- `/admin` de la faculté accepte son personnel et refuse les autres.
- `/admin` du superadmin liste toutes les facultés et leurs statistiques.
