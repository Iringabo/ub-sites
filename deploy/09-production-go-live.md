# 09 — Production go-live checklist (mandatory)

Use this **after** you finish a host guide (Oracle, InfinityFree, AWS, …).  
A site is **not** production-ready until every blocking item below is green.

This matches the project’s own command:

```bash
php spark app:production-check
php spark app:production-check --strict
```

Also read: `template/docs/GUIDE_INSTALLATION_DEPLOIEMENT.md`, `template/docs/SECURITY_REVIEW.md`.

---

## A. Architecture (must)

- [ ] Document root is each instance’s **`public/`** — never the project root
- [ ] `.env`, `app/`, `vendor/`, `writable/` are **not** web-accessible
- [ ] One **shared** MySQL/MariaDB database for all faculty + superadmin folders
- [ ] Each faculty folder has its own `app.siteSlug` + unique session cookie names
- [ ] Superadmin has `app.centralAdminMode = true` and visitors to `/` go to `/admin`

## B. `.env` production values (must)

Use templates:

- [env.production.faculty.example](env.production.faculty.example)
- [env.production.superadmin.example](env.production.superadmin.example)

| Setting | Required value |
|---------|----------------|
| `CI_ENVIRONMENT` | `production` |
| `app.baseURL` | Final `https://…/` URL with trailing slash |
| `app.forceGlobalSecureRequests` | `true` |
| `app.CSPEnabled` | `true` |
| `database.default.DBDebug` | `false` |
| `encryption.key` | Generated on server (`php spark key:generate`) — unique per instance |
| `app.requireKnownHostname` | `true` on public internet hosts |
| Local demo passwords | **Gone** (never `LocalAdmin!2026` / local DB passwords) |

- [ ] `php spark key:generate` run on **each** instance
- [ ] Faculty cookies: `ci_session_{slug}` / `remember_{slug}`
- [ ] Superadmin cookies: `ci_session_central` / `remember_central`
- [ ] Superadmin `app.uploadMirrors` points at real absolute paths
- [ ] `app.proxyIPs` set only if behind a trusted reverse proxy

## C. PHP / server (must)

- [ ] PHP **8.2+** (8.3 preferred)
- [ ] Extensions: `intl` `mbstring` `mysqli` `curl` `gd` `fileinfo` `json` `dom` `xml` `zip`
- [ ] `composer install --no-dev --optimize-autoloader` on each instance
- [ ] HTTPS certificate valid (Let’s Encrypt / AutoSSL)
- [ ] HTTP → HTTPS redirect works

## D. Nginx / Apache hardening (must on VPS)

### Nginx

- [ ] `root` → `…/public`
- [ ] `try_files $uri $uri/ /index.php?$query_string;`
- [ ] Include [nginx/uploads-security.conf](nginx/uploads-security.conf) so `/uploads/*.php` cannot execute
- [ ] Deny access to dotfiles

### Apache / cPanel

- [ ] vhost / subdomain document root → `public/`
- [ ] Keep `public/uploads/.htaccess` from the project
- [ ] `mod_rewrite` enabled

## E. Permissions (must)

Web user (often `www-data`) must write:

```text
writable/
writable/cache writable/logs writable/session writable/uploads
public/uploads/
```

```bash
# example on Ubuntu
sudo chown -R www-data:www-data writable public/uploads
sudo find writable public/uploads -type d -exec chmod 775 {} \;
sudo find writable public/uploads -type f -exec chmod 664 {} \;
```

- [ ] Avoid `chmod 777` except as a 5-minute debug, then revert

## F. Database (must)

- [ ] Database charset `utf8mb4`
- [ ] App DB user is **not** root; password is strong and unique
- [ ] MySQL port **3306 not open** to the public internet
- [ ] Migrations applied once: `php spark migrate --all`
- [ ] Backup taken **before** any later migration

```bash
mysqldump -u ubapp -p ub_shared > backup-$(date +%F).sql
```

## G. Accounts & content (must)

- [ ] Create production superadmin / faculty admins with **new** passwords
- [ ] Remove or disable any leftover local demo accounts if you imported a dump
- [ ] Site domains in admin match real hostnames (`app.requireKnownHostname=true`)
- [ ] Optional: `php spark site:seed-demo --force` only if you accept overwriting demo content

## H. Automated check (must pass)

On **every** instance:

```bash
php spark app:production-check
```

Before handover / strict acceptance:

```bash
php spark app:production-check --strict
```

- [ ] No blocking errors
- [ ] Warnings reviewed and accepted in writing (e.g. empty `proxyIPs` when no proxy)

## I. Functional smoke test (must)

From a private browser window:

- [ ] Faculty home loads on HTTPS
- [ ] `/login` works; wrong password fails cleanly
- [ ] Faculty `/admin` works for that faculty only
- [ ] Superadmin cannot be used as a public marketing site (`/` → `/admin`)
- [ ] Contact form behaves (and SMTP works if enabled)
- [ ] Upload an image in admin; it appears on the public site
- [ ] Staff / news / alumni pages load without 500s
- [ ] Language switch FR/EN still works if you use translations

## J. Operations (should)

- [ ] Off-site backup schedule (DB daily + `public/uploads` daily/weekly)
- [ ] Log rotation for `writable/logs/`
- [ ] OS security updates enabled (`unattended-upgrades` on Ubuntu)
- [ ] Billing / free-tier expiry calendar reminder (AWS/Azure/GCP/DO)
- [ ] Who has SSH / panel access documented

## K. Do **not** ship

- [ ] `CI_ENVIRONMENT = development`
- [ ] `database.default.DBDebug = true`
- [ ] Local `.env` from this laptop
- [ ] Open directory listing on `/uploads`
- [ ] Executable PHP under `/uploads`
- [ ] Shared `encryption.key` across instances
- [ ] Same session cookie name on two faculties

---

## Definition of done

Production-ready means:

1. Host guide completed  
2. Templates in `env.production.*.example` applied  
3. `app:production-check` has **no blocking errors**  
4. Smoke tests (section I) pass on the real HTTPS URLs  
5. Backup + admin passwords are handled outside Git  

If any of those fail, the platform is **not** ready for users.
