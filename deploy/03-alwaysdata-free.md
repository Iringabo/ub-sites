# 03 — Alwaysdata Free: six sites, one database

**You already have a Free account.** This guide starts from the panel, not from signup.

**What you will have:** five faculty sites plus superadmin, each in its own folder, all reading and writing the **same** MariaDB database. Content is separated by `site_id`, not by a second database.

**Public addresses** (replace `ACCOUNT` with the name Alwaysdata gave you, visible at the top of https://admin.alwaysdata.com/):

| Folder | Address | Files on the server | Document root |
|--------|---------|---------------------|---------------|
| `fseg` | `https://fseg.ACCOUNT.alwaysdata.net/` | `~/www/fseg/` | `www/fseg/public` |
| `fsi` | `https://fsi.ACCOUNT.alwaysdata.net/` | `~/www/fsi/` | `www/fsi/public` |
| `med` | `https://med.ACCOUNT.alwaysdata.net/` | `~/www/med/` | `www/med/public` |
| `fabi` | `https://fabi.ACCOUNT.alwaysdata.net/` | `~/www/fabi/` | `www/fabi/public` |
| `flsh` | `https://flsh.ACCOUNT.alwaysdata.net/` | `~/www/flsh/` | `www/flsh/public` |
| `superadmin` | `https://admin.ACCOUNT.alwaysdata.net/` | `~/www/superadmin/` | `www/superadmin/public` |

Do not publish the `template/` folder.

Official limits (Free, Public Cloud): [pricing](https://help.alwaysdata.com/en/docs/admin-billing/billing/public-cloud-prices/) and [suspension rules](https://help.alwaysdata.com/en/docs/admin-billing/profile/suspension/).

## Read this before uploading

- **One account, many sites.** Alwaysdata allows as many websites and databases as you want on one account. Isolation is per account, which is what this platform expects.
- **No custom domain on Free.** Only addresses under your `ACCOUNT.alwaysdata.net` name. A university domain needs a paid plan.
- **1 GB total.** That quota includes files, the database, and mail. Six copies of `vendor/` fit only if you skip `.git`, `tests/`, local logs, and large demo photo packs. If the panel shows the disk full, stop uploading and delete those extras.
- **256 MB RAM, a fraction of a CPU.** Fine for a demo. A heavy photo import can fail.
- **Personal / non-commercial use.** A university production site that takes payments or ads is outside the Free terms.
- **Log into the panel regularly.** A Free profile that stays unused is suspended. Signing in at https://admin.alwaysdata.com/ turns it back on.
- **Do not run `php spark test` on this server.** The test bootstrap drops and recreates its database.

## Step 1 — Note the names the panel already shows

Open https://admin.alwaysdata.com/ and write down:

1. **Account name** (`ACCOUNT`). SSH user and home directory are `/home/ACCOUNT`.
2. **Web > Sites.** A default site on `ACCOUNT.alwaysdata.net` pointing at `www/` is usually already there. Leave it for now. You will not use that folder for a faculty.
3. **Remote access > SSH.** Host is `ssh-ACCOUNT.alwaysdata.net`, user is `ACCOUNT`, port `22`. Enable SSH if the switch is off, and set a password or add your public key.
4. You will copy the MySQL hostname in Step 3. It is **not** `localhost`.

From your computer:

```bash
ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net
```

`pwd` should print `/home/ACCOUNT`. Type `exit` when you only needed to confirm the login.

## Step 2 — PHP 8.3 for the panel and for SSH

1. Panel → **Environment > PHP**.
2. Set the account PHP version to **8.3** (8.2 is the minimum this project accepts).
3. Save.

SSH `php -v` must show 8.3 before you run `spark`. If it shows an older version, the Environment menu was not saved.

`composer` is available on Alwaysdata. If `composer -V` fails, install it in your home directory from https://getcomposer.org/download/ and call `php ~/composer.phar` instead of `composer` in the commands below.

## Step 3 — One shared database

1. Panel → **Databases > MySQL > Add a database**.
2. Name: `ub_shared`. The panel stores it as `ACCOUNT_ub_shared`. Copy that full name.
3. Create a user (for example `ubapp`). The panel stores it as `ACCOUNT_ubapp`. Give that user **all rights on this database only**.
4. Copy the **hostname** shown on the database page. It looks like `mysql-ACCOUNT.alwaysdata.net`.
5. Set a long password and keep it. The same four values go into every site’s `.env`:

```text
hostname = mysql-ACCOUNT.alwaysdata.net
database = ACCOUNT_ub_shared
username = ACCOUNT_ubapp
password = the password you just set
```

Create this database **once**. Do not create `ub_fseg`, `ub_fsi`, and so on.

## Step 4 — Build the six folders on your computer

From the repository root, in each of `fseg`, `fsi`, `med`, `fabi`, `flsh`, and `superadmin`:

```bash
cd fseg
composer install --no-dev --optimize-autoloader
cd ..
```

Repeat for the other five folders. Each folder must keep its **own** `vendor/`. Do not symlink `vendor` between them: the autoloader would load another folder’s `app/`.

Do not upload:

- any local `.env`
- `template/`
- `.git/`
- `tests/`
- `writable/cache`, `writable/logs`, `writable/session` contents
- `LOCAL_CREDENTIALS.md`

## Step 5 — Upload the six folders

On the server:

```bash
ssh ACCOUNT@ssh-ACCOUNT.alwaysdata.net
mkdir -p ~/www
exit
```

From your computer, once per folder (example for `fseg`; repeat for `fsi`, `med`, `fabi`, `flsh`, `superadmin`):

```bash
rsync -avz --delete \
  --exclude '.env' \
  --exclude '.git' \
  --exclude 'tests' \
  --exclude 'writable/cache' \
  --exclude 'writable/logs' \
  --exclude 'writable/session' \
  ./fseg/ ACCOUNT@ssh-ACCOUNT.alwaysdata.net:~/www/fseg/
```

Run `rsync` from the repository root so `./fseg/` is the faculty folder. After all six uploads, SSH in and check:

```bash
ls ~/www
# fabi  flsh  fseg  fsi  med  superadmin
test -f ~/www/fseg/public/index.php && test -f ~/www/superadmin/spark && echo OK
```

## Step 6 — Six websites in the panel

For **each** row in the table at the top of this file:

1. Panel → **Web > Sites > Add a site**.
2. Type: **PHP**.
3. **Addresses:** type the label, then pick your account domain. For FSEG the address must become `fseg.ACCOUNT.alwaysdata.net`. Repeat with `fsi`, `med`, `fabi`, `flsh`, and `admin`.
4. **Path / directory** (relative to `/home/ACCOUNT`): `www/fseg/public` for FSEG, and the matching `www/<folder>/public` for the others. This path is the document root. It must be `public`, not the folder that contains `.env`.
5. PHP version: **8.3**, same as Step 2.
6. SSL: let Alwaysdata issue the certificate for that address (Let’s Encrypt / the SSL tab on the site).

The panel does not create the directory. Step 5 already did.

The default site on `ACCOUNT.alwaysdata.net` (folder `www/`) is not one of the six. Either delete it or set its type to **Redirect** toward `https://fseg.ACCOUNT.alwaysdata.net/`. Do not point it at a faculty root that contains `.env`.

If the address field refuses `fseg.ACCOUNT.alwaysdata.net` and only accepts the bare `ACCOUNT.alwaysdata.net`, stop. The Free plan would then be offering a single hostname, and these six sites cannot be separated. Check **Web > Sites** again after a panel refresh before changing plan.

## Step 7 — One `.env` per folder, same database

SSH in. Do not copy a local `.env` up.

The database block is **identical** in all six files. Everything else changes per folder.

### Faculty example (`~/www/fseg/.env`)

Copy [env.production.faculty.example](env.production.faculty.example) and set at least:

```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://fseg.ACCOUNT.alwaysdata.net/'
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
app.siteSlug = fseg
app.centralAdminMode = false
app.allowedHostnames = fseg.ACCOUNT.alwaysdata.net
app.requireKnownHostname = true

database.default.hostname = mysql-ACCOUNT.alwaysdata.net
database.default.database = ACCOUNT_ub_shared
database.default.username = ACCOUNT_ubapp
database.default.password = YOUR_DB_PASSWORD
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
database.default.DBDebug = false

session.cookieName = ci_session_fseg
session.rememberCookieName = remember_fseg

# {slug} becomes fseg, fsi, med, fabi, flsh when you run site:create.
app.publicHostPattern = {slug}.ACCOUNT.alwaysdata.net
```

`app.baseURL` needs the trailing slash. Cookie names must differ per site or logging into FSEG logs you out of FSI.

Repeat for the other faculties. Only these fields change:

| Folder | `app.baseURL` host | `app.siteSlug` | `app.allowedHostnames` | session cookie | remember cookie |
|--------|--------------------|----------------|------------------------|----------------|-----------------|
| fseg | `fseg.ACCOUNT.alwaysdata.net` | `fseg` | same host | `ci_session_fseg` | `remember_fseg` |
| fsi | `fsi.ACCOUNT.alwaysdata.net` | `fsi` | same host | `ci_session_fsi` | `remember_fsi` |
| med | `med.ACCOUNT.alwaysdata.net` | `med` | same host | `ci_session_med` | `remember_med` |
| fabi | `fabi.ACCOUNT.alwaysdata.net` | `fabi` | same host | `ci_session_fabi` | `remember_fabi` |
| flsh | `flsh.ACCOUNT.alwaysdata.net` | `flsh` | same host | `ci_session_flsh` | `remember_flsh` |

Leave `app.centralAdminMode = false` and `app.uploadMirrors` empty on every faculty.

`app.publicHostPattern` is the **same line in all six** `.env` files, including superadmin:

```ini
app.publicHostPattern = {slug}.ACCOUNT.alwaysdata.net
```

`site:create` reads that line from the folder you run it in and replaces `{slug}`. There is no `fseg.test` default in the code. An empty pattern stores no public host, and the site then answers “Aucun site actif ne correspond à cet hôte.”

### Superadmin (`~/www/superadmin/.env`)

Copy [env.production.superadmin.example](env.production.superadmin.example). Same `database.default.*` as the faculties. Then:

```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://admin.ACCOUNT.alwaysdata.net/'
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
app.siteSlug =
app.centralAdminMode = true
app.defaultSiteSlug = fseg
app.allowedHostnames = admin.ACCOUNT.alwaysdata.net
app.requireKnownHostname = true

session.cookieName = ci_session_central
session.rememberCookieName = remember_central

app.uploadMirrors = fseg:/home/ACCOUNT/www/fseg,fsi:/home/ACCOUNT/www/fsi,med:/home/ACCOUNT/www/med,fabi:/home/ACCOUNT/www/fabi,flsh:/home/ACCOUNT/www/flsh

app.previewBase.fseg = https://fseg.ACCOUNT.alwaysdata.net/
app.previewBase.fsi = https://fsi.ACCOUNT.alwaysdata.net/
app.previewBase.med = https://med.ACCOUNT.alwaysdata.net/
app.previewBase.fabi = https://fabi.ACCOUNT.alwaysdata.net/
app.previewBase.flsh = https://flsh.ACCOUNT.alwaysdata.net/

app.publicHostPattern = {slug}.ACCOUNT.alwaysdata.net
```

`app.centralAdminMode = true` is what makes this folder the superadmin. Public URLs on that host redirect to `/admin`.

### Encryption key, once per folder

```bash
cd ~/www/fseg && php spark key:generate
cd ~/www/fsi && php spark key:generate
cd ~/www/med && php spark key:generate
cd ~/www/fabi && php spark key:generate
cd ~/www/flsh && php spark key:generate
cd ~/www/superadmin && php spark key:generate
```

Each folder gets its own `encryption.key`. Do not paste one key into all six files.

## Step 8 — Permissions

The SSH user is also the user PHP runs as, so you do not need `www-data`.

```bash
for dir in fseg fsi med fabi flsh superadmin; do
  mkdir -p ~/www/$dir/writable/{cache,logs,session,uploads} ~/www/$dir/public/uploads
  chmod -R u+rwX ~/www/$dir/writable ~/www/$dir/public/uploads
done
```

## Step 9 — Migrate once, then register the five faculties

Migrations change the shared database. Run them **one time**, from any faculty folder whose `.env` already points at `ACCOUNT_ub_shared`:

```bash
cd ~/www/fseg
php spark migrate --all
```

`site:create` inserts the faculty row, starter content, and the public hostname. Run it from a folder whose `.env` already contains `app.publicHostPattern = {slug}.ACCOUNT.alwaysdata.net` (Step 7). `~/www/fseg` is fine:

```bash
cd ~/www/fseg
php spark site:create --identifier fseg --slug fseg --name "Faculté des Sciences Économiques et de Gestion"
php spark site:create --identifier fsi --slug fsi --name "Faculté des Sciences et Ingénierie"
php spark site:create --identifier med --slug med --name "Faculté de Médecine"
php spark site:create --identifier fabi --slug fabi --name "Faculté d’Agronomie et de Bioingénierie"
php spark site:create --identifier flsh --slug flsh --name "Faculté des Lettres et des Sciences Humaines"
```

Each command must print a line like:

```text
Hôtes publics : fseg.ACCOUNT.alwaysdata.net
```

The five hosts are `fseg`, `fsi`, `med`, `fabi`, and `flsh` under `.ACCOUNT.alwaysdata.net`. If you see “Aucun hôte public enregistré” instead, the pattern was missing in that folder’s `.env`. Add it and run the same `site:create` again; it updates the existing faculty.

To set one host without the pattern:

```bash
php spark site:create --identifier fseg --slug fseg \
  --name "Faculté des Sciences Économiques et de Gestion" \
  --hostname fseg.ACCOUNT.alwaysdata.net
```

Do not give the superadmin host a `sites` row. Superadmin selects a faculty inside `/admin`. The pattern is still worth putting in `~/www/superadmin/.env` so a later `site:create` from that folder stores the same Alwaysdata hosts.

## Step 10 — One admin per faculty, then the superadmin

Passwords are read from the environment so they do not sit in shell history as command arguments. Use a different password for each person. Usernames are letters, digits, and dots only (no hyphens).

From each faculty folder (`app.centralAdminMode` must be `false`):

```bash
cd ~/www/fseg
PLATFORM_ADMIN_PASSWORD='replace-with-a-long-password' \
  php spark admin:create-faculty-admin \
  --email fseg-admin@example.com \
  --username fsegadmin \
  --password-env PLATFORM_ADMIN_PASSWORD
```

Repeat in `~/www/fsi`, `~/www/med`, `~/www/fabi`, and `~/www/flsh` with that folder’s email and username. The command refuses to run in `superadmin/`.

From the superadmin folder only:

```bash
cd ~/www/superadmin
PLATFORM_ADMIN_PASSWORD='replace-with-another-long-password' \
  php spark admin:create-superadmin \
  --email superadmin@example.com \
  --username superadmin \
  --password-env PLATFORM_ADMIN_PASSWORD
```

A faculty admin signs in on their own host and can create editors there. The superadmin signs in on `admin.ACCOUNT.alwaysdata.net` and can create or delete faculty admins under **Comptes**.

## Step 11 — Check each site

On every folder:

```bash
cd ~/www/fseg
php spark app:production-check
php spark app:production-check --strict
```

Repeat for `fsi`, `med`, `fabi`, `flsh`, and `superadmin`. Fix anything the command reports before sharing the URLs.

Then in a browser:

- [ ] `https://fseg.ACCOUNT.alwaysdata.net/` shows the FSEG public site, not a directory listing and not another faculty.
- [ ] The same for `fsi`, `med`, `fabi`, and `flsh`.
- [ ] `https://fseg.ACCOUNT.alwaysdata.net/admin` accepts the FSEG admin and does not show FSI content.
- [ ] `https://admin.ACCOUNT.alwaysdata.net/` redirects to `/admin` and accepts only the superadmin.
- [ ] Creating a news item on FSEG does not appear on FSI.
- [ ] Uploading a file works (`public/uploads` and `writable` are writable).
- [ ] `https://ACCOUNT.alwaysdata.net/.env` does not download a file. If it does, the default site’s document root is wrong.

Then complete [09-production-go-live.md](09-production-go-live.md).

## When something fails

| What you see | What to change |
|--------------|----------------|
| Directory listing of `app/` or a download of `.env` | Site path must be `www/<folder>/public`, not `www/<folder>`. |
| “Aucun site actif ne correspond à cet hôte.” | `app.publicHostPattern` was empty when you ran `site:create`. Set it to `{slug}.ACCOUNT.alwaysdata.net` and run `site:create` again for that faculty. |
| Faculty admin panel says only a superadmin may enter | That folder’s `.env` has `app.centralAdminMode = true`, or the site address is the superadmin host. |
| Superadmin public pages do not redirect to `/admin` | `~/www/superadmin/.env` needs `app.centralAdminMode = true`. |
| Database connection error | Hostname, database, and user must be the **prefixed** names from the panel (`ACCOUNT_ub_shared`, `mysql-ACCOUNT.alwaysdata.net`). |
| Blank page after login on a second faculty | Cookie names are identical in two `.env` files. |
| Disk quota | Remove `tests/`, `.git/`, and old files under `writable/logs`. Six `vendor/` trees are required. |
| `php spark` uses PHP 7 or 8.0 | Environment > PHP is not 8.3, or you opened a new SSH session before saving it. |

## Stay inside the Free plan

- Sign in at https://admin.alwaysdata.com/ often enough that Alwaysdata does not treat the profile as abandoned.
- Do not open a second Free account to get more disk. Alwaysdata suspends multiple free profiles.
- Custom domains, more than 1 GB, and more RAM require a paid plan (**Subscriptions** in the panel).
