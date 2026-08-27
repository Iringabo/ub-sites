# Guide De Deploiement cPanel

Derniere actualisation documentaire : 16 aout 2026.

Ce guide explique comment publier la plateforme FSEG sur un hebergement mutualise Apache/cPanel avec PHP 8.2+ et MySQL/MariaDB. Il complete le guide general de deploiement.

## 1. Prerequis cPanel

Verifier dans cPanel :

- PHP 8.2 ou plus recent;
- extensions PHP : `intl`, `mbstring`, `mysqli`, `fileinfo`, `json`, `dom`, `xmlwriter`, `curl`;
- MySQL ou MariaDB disponible;
- acces Terminal/SSH ou possibilite d'executer Composer localement;
- gestion du document root du domaine ou sous-domaine;
- HTTPS active via AutoSSL ou certificat equivalent.

Si cPanel ne fournit pas Composer ou Terminal, executer `composer install --no-dev --optimize-autoloader` en local puis televerser aussi le dossier `vendor/`.

## 2. Structure Recommandee

La meilleure configuration consiste a placer le projet hors du web root et a pointer le domaine vers `public/`.

Exemple :

```text
/home/compte/fseg-app/          racine complete du projet
/home/compte/fseg-app/public/   document root du domaine
```

Dans cPanel, configurer le domaine ou sous-domaine avec :

```text
Document Root = /home/compte/fseg-app/public
```

Ne jamais pointer le domaine vers la racine du projet. Les dossiers `app/`, `vendor/`, `writable/` et le fichier `.env` ne doivent pas etre accessibles depuis le web.

## 3. Cas Fallback Avec public_html

Certains hebergements ne permettent pas de changer le document root du domaine principal. Dans ce cas :

1. placer l'application hors web root, par exemple `/home/compte/fseg-app`;
2. copier uniquement le contenu de `public/` dans `/home/compte/public_html`;
3. conserver les assets et `.htaccess` publics dans `public_html`;
4. modifier la ligne de chargement des chemins dans `/home/compte/public_html/index.php`.

Remplacer :

```php
require FCPATH . '../app/Config/Paths.php';
```

par un chemin absolu adapte :

```php
require '/home/compte/fseg-app/app/Config/Paths.php';
```

Cette methode fonctionne, mais la configuration document root vers `public/` reste plus propre.

## 4. Preparation Des Fichiers

Depuis votre machine locale ou le Terminal cPanel :

```bash
composer install --no-dev --optimize-autoloader
```

Televerser ensuite le projet par Git, File Manager ou FTP/SFTP. Eviter d'envoyer :

- `.git/` si l'hebergeur n'utilise pas Git;
- fichiers de cache locaux;
- fichiers `.env` provenant d'un autre environnement;
- fichiers de tests si l'espace disque est limite, sauf besoin de recette technique.

Le fichier `env` ne doit jamais servir de modele. Utiliser `.env.example` comme reference et creer un nouveau `.env` propre sur l'hebergement.

## 5. Creation De La Base MySQL

Dans cPanel :

1. ouvrir **MySQL Databases** ou **MySQL Database Wizard**;
2. creer une base, par exemple `compte_fseg`;
3. creer un utilisateur SQL, par exemple `compte_fseg_user`;
4. attribuer tous les privileges a cet utilisateur sur la base;
5. noter le nom exact de l'hote MySQL indique par cPanel, souvent `localhost`.

La base doit utiliser `utf8mb4` si cPanel permet de choisir l'encodage. Sinon, les migrations creeront les tables avec l'encodage attendu lorsque le serveur le permet.

## 6. Configuration .env Production

Creer un fichier `.env` a la racine du projet, hors `public_html` si possible :

```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://votre-domaine.bi/'
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
app.defaultLocale = fr
app.appTimezone = Africa/Bujumbura
app.siteSlug = fseg

database.default.hostname = localhost
database.default.database = compte_fseg
database.default.username = compte_fseg_user
database.default.password = mot-de-passe-fort
database.default.DBDriver = MySQLi
database.default.DBDebug = false
database.default.port = 3306

encryption.key = cle-secrete-generee
```

Generer `encryption.key` dans un environnement sur :

```bash
php spark key:generate
```

Si la commande n'est pas disponible sur cPanel, generer la cle localement dans un `.env` temporaire, copier uniquement la valeur de `encryption.key`, puis supprimer le temporaire.

## 7. Migrations Et Initialisation

Avec Terminal/SSH cPanel :

```bash
php spark migrate --all
```

Pour une premiere installation avec donnees de demonstration seulement :

```bash
# (optionnel) php spark db:seed TemplateStarterSeeder — contenu de départ neutre
```

Creer ensuite le premier superadministrateur :

```bash
php spark admin:create-superadmin
```

Si cPanel ne permet pas d'executer `spark`, preparer la base dans un environnement local equivalent, exporter un dump SQL avec phpMyAdmin ou `mysqldump`, puis importer le dump dans phpMyAdmin cPanel. Cette methode doit rester reservee aux hebergements sans terminal.

## 8. Permissions

Le processus PHP doit pouvoir ecrire dans :

```text
writable/
public/uploads/
```

Permissions usuelles sur hebergement mutualise :

```bash
chmod -R 775 writable public/uploads
```

Si l'hebergeur refuse `775`, utiliser l'outil File Manager cPanel pour donner les droits d'ecriture au proprietaire du compte. Eviter `777` sauf diagnostic temporaire tres court.

## 9. HTTPS, Domaines Et Multi-Site

Activer AutoSSL dans cPanel, puis verifier que le site charge en HTTPS.

Pour une plateforme multi-facultes :

- creer les sous-domaines dans cPanel : `fseg.example.bi`, `fsi.example.bi`, `admin.example.bi`;
- faire pointer chaque sous-domaine vers le meme dossier `public/`;
- renseigner ces hostnames dans l'administration centrale;
- configurer dans `.env` :

```ini
app.allowedHostnames = admin.example.bi,fseg.example.bi,fsi.example.bi
app.centralAdminHosts = admin.example.bi
app.requireKnownHostname = true
```

Adapter les domaines a l'infrastructure reelle.

## 10. Verification Apres Upload

Executer :

```bash
composer validate --no-check-publish
php spark routes
php spark migrate:status
php spark app:production-check
```

Tester dans le navigateur :

- page d'accueil;
- `/admin`;
- formulaire contact;
- upload d'image depuis l'administration;
- `/healthz`.

Le controle production ne doit contenir aucune erreur bloquante avant livraison.

## 11. Depannage

| Symptome | Cause probable | Correction |
|---|---|---|
| Erreur 500 | PHP trop ancien, extension manquante, permissions ou `.env` invalide. | Verifier version PHP, logs cPanel, `writable/logs/` et extensions. |
| Page 404 sur toutes les routes sauf accueil | `.htaccess` absent ou `mod_rewrite` desactive. | Conserver le `.htaccess` de `public/` et activer les pretty URLs cote hebergeur. |
| Erreur base de donnees | Identifiants cPanel incorrects ou privileges manquants. | Verifier hostname, nom prefixe de base, utilisateur et droits. |
| Assets CSS/JS manquants | Mauvais document root ou copie incomplete de `public/`. | Pointer vers `public/` ou recopier tous les assets publics. |
| Upload impossible | `public/uploads/` non inscriptible. | Corriger permissions et verifier quota disque. |
| Admin inaccessible sur sous-domaine | Hostname non declare ou affectation utilisateur absente. | Ajouter le hostname au site et verifier `user_sites`. |

## 12. Sauvegardes

Avant chaque migration en production :

- exporter la base depuis phpMyAdmin;
- sauvegarder `public/uploads/`;
- sauvegarder le `.env` dans un coffre de secrets, jamais dans Git;
- noter la version du code deploye.

Programmer ensuite des sauvegardes regulieres via cPanel Backup, JetBackup ou l'outil fourni par l'hebergeur.
