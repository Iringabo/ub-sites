# 04 — AwardSpace Free (shared PHP forever)

**Cost:** Free hosting plan (long-term / free tier — confirm current quota on their site)  
**Type:** Shared hosting + control panel  
**Fits:** Single faculty tryout  

Site: https://www.awardspace.com/

## Why this option

AwardSpace offers a free web hosting tier with PHP and MySQL suitable for learning deployments when you do not need a VPS.

## Limits (typical of free shared hosts)

- Disk / traffic quotas
- Free subdomain or limited domain features
- Ads or upgrade prompts may exist on some free plans — check the current offer page
- Often no SSH → prepare `vendor/` locally and use FTP + phpMyAdmin

Always re-read their Free Hosting page before signup; quotas change.

## Step 1 — Sign up for Free Hosting

1. Open https://www.awardspace.com/
2. Choose **Free Hosting**
3. Create an account and a site / subdomain

## Step 2 — Collect connection details

From the control panel note:

- FTP host / user / password
- PHP version (set to **8.2+** if selectable)
- MySQL database name, user, password, host

## Step 3 — Prepare one faculty locally

```bash
cd fseg
composer install --no-dev --optimize-autoloader
```

See [00-prepare-locally.md](00-prepare-locally.md).

## Step 4 — Create MySQL database

1. Panel → MySQL
2. Create DB + user
3. Assign privileges

## Step 5 — Upload the application

Via FTP:

1. Create folder `fseg-app` for the full CodeIgniter tree
2. Put `public/` contents into the web root (`public_html` / `httpdocs`) **or** set the domain document root to `fseg-app/public` if the panel allows

If you must use `public_html` as root, adjust `index.php` Paths require like in the InfinityFree guide.

## Step 6 — Configure `.env`

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://YOUR-FREE-SUBDOMAIN/'
app.forceGlobalSecureRequests = true
app.siteSlug = fseg
app.centralAdminMode = false
session.cookieName = ci_session_fseg
session.rememberCookieName = remember_fseg

database.default.hostname = localhost
database.default.database = YOUR_DB
database.default.username = YOUR_USER
database.default.password = YOUR_PASSWORD
database.default.DBDriver = MySQLi
```

If `localhost` fails, use the MySQL hostname shown in the panel.

## Step 7 — Import schema

Without SSH:

1. On your PC, point a temporary local `.env` at an empty local DB and run `php spark migrate --all`
2. Export SQL dump
3. Import with AwardSpace phpMyAdmin

With SSH (if available): run `php spark migrate --all` on the server.

## Step 8 — Writable permissions

In the file manager, ensure these are writable by PHP:

- `writable/` (and subfolders)
- `public/uploads/`

## Step 9 — Verify

- [ ] Home page loads
- [ ] CSS/JS load
- [ ] Login works
- [ ] Admin can save a simple page

## When to leave AwardSpace

If you need all six instances, custom domains without ads, or stable CPU for demos with many images, move to [01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md).

## Production gate (required)

Complete [09-production-go-live.md](09-production-go-live.md) and apply [env.production.faculty.example](env.production.faculty.example). Free shared hosting is still production only if HTTPS, `DBDebug=false`, and secrets are correct.
