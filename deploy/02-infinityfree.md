# 02 — InfinityFree (free shared PHP forever)

**Cost:** Free forever (no credit card required for free hosting)  
**Type:** Shared hosting (control panel + FTP)  
**Fits:** One faculty site for demo / learning (full multi-folder platform is awkward here)  

Site: https://www.infinityfree.com/

## Why this option

- PHP 8.3 + MySQL
- Free subdomain
- No ads injected on your site (per their marketing)
- Good for testing **one** instance (e.g. `fseg`)

## Limits

- Soft resource limits (CPU / hits) — not for heavy production traffic
- MySQL host is **not** `localhost` (use the host shown in the panel, e.g. `sqlXXX.infinityfree.com`)
- No remote MySQL from your home PC
- SSH / Composer on server may be unavailable — upload `vendor/` from your PC
- Email via PHP `mail()` is limited/disabled — use an external SMTP later if needed

## Step 1 — Create an account

1. Open https://www.infinityfree.com/
2. Sign up / create a free hosting account
3. Create a website (choose a free subdomain, e.g. `myfseg.infinityfreeapp.com`)

## Step 2 — Note control panel values

In the client area / control panel, write down:

- FTP host, username, password
- Domain / subdomain
- MySQL: database name, username, password, **hostname** (important)

## Step 3 — Prepare files on your PC

Follow [00-prepare-locally.md](00-prepare-locally.md) for **one faculty** (example `fseg`).

```bash
cd fseg
composer install --no-dev --optimize-autoloader
```

## Step 4 — Document root strategy

InfinityFree usually serves `htdocs/` (or similar) as the web root.

**Recommended approach (fallback style):**

1. Upload the full app into a folder **outside** or beside web, if the panel allows  
   OR upload everything into `htdocs/app-root/` and put only `public/` contents into `htdocs/`.

Practical pattern many people use:

```text
htdocs/
  index.php          ← from public/index.php (paths adjusted)
  .htaccess          ← from public/.htaccess
  assets/            ← from public/assets
  uploads/           ← from public/uploads
  ...
../fseg-app/         ← if available, full CI4 tree
```

If you **cannot** place code outside `htdocs`:

1. Upload full project into `htdocs/fseg-app/`
2. Copy contents of `htdocs/fseg-app/public/` into `htdocs/`
3. Edit `htdocs/index.php` so Paths.php points to the real app:

```php
require '/home/volXX_Y/infinityfree.com/USERNAME/fseg-app/app/Config/Paths.php';
```

(Use the real absolute path shown in the file manager.)

Never leave `.env` downloadable from the web. Keep it in the app root, not in `htdocs/` public files.

## Step 5 — Create the MySQL database

1. Control panel → **MySQL Databases**
2. Create database + user
3. Assign all privileges
4. Copy the **MySQL hostname** (not localhost)

## Step 6 — Configure `.env` on the server

Place `.env` in the application root (next to `app/`):

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://myfseg.infinityfreeapp.com/'
app.forceGlobalSecureRequests = true
app.siteSlug = fseg
app.centralAdminMode = false
session.cookieName = ci_session_fseg
session.rememberCookieName = remember_fseg

database.default.hostname = sqlXXX.infinityfree.com
database.default.database = YOUR_DB
database.default.username = YOUR_USER
database.default.password = YOUR_PASSWORD
database.default.DBDriver = MySQLi
database.default.port = 3306
```

## Step 7 — Upload with FTP

Use FileZilla / WinSCP:

1. Connect with InfinityFree FTP credentials
2. Upload `vendor/`, `app/`, `writable/`, `public/` assets as planned
3. Ensure `writable/` is writable (permissions 755/775 as allowed)

## Step 8 — Run migrations

If the panel has **Terminal / SSH**:

```bash
cd ~/fseg-app
php spark migrate --all
```

If **no terminal**:

1. Run migrations on a local MySQL with the same schema
2. Export SQL
3. Import via phpMyAdmin in InfinityFree

Then create the faculty admin locally or via an imported Shield user table (prefer running `php spark` when possible).

## Step 9 — Verify

- [ ] `https://your-subdomain/` shows the faculty home
- [ ] Static assets load (`/assets/css/style.css`)
- [ ] Login page opens
- [ ] No database “localhost” connection error

## Tips for this project

- Deploy **one** faculty first. Multi-folder on free shared hosting means several accounts or messy path tricks.
- Skip `site:seed-demo` on InfinityFree if inodes/disk are tight; add a few pages manually in `/admin`.
- Soft limits: if the site sleeps or throttles, that is expected on free shared hosts.

## Production gate (required)

Even on free shared hosting, finish:

1. `.env` from [env.production.faculty.example](env.production.faculty.example) (`DBDebug=false`, HTTPS, unique `encryption.key`)
2. [09-production-go-live.md](09-production-go-live.md)
3. `php spark app:production-check` when SSH/terminal exists; otherwise verify the checklist manually
