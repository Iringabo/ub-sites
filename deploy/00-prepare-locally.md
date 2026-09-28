# 00 — Prepare the project locally (all hosts)

Do these steps **once** on your computer before uploading to any free host.  
This step prepares a **production build**, not a copy of your laptop demo.

## Goal

Ship a production-ready tree: optimized Composer deps, no local secrets, correct
instance roles. Final hardening happens on the server with
[09-production-go-live.md](09-production-go-live.md).

## Prerequisites on your PC

- PHP 8.2+ and Composer
- Git (optional but useful)
- Access to this repository (`fseg-main-multifolder`)

## Step 1 — Choose what you will deploy

| Choice | Upload | Database |
|--------|--------|----------|
| **A. One faculty** (InfinityFree / Alwaysdata / AwardSpace) | e.g. `fseg/` only | One MySQL DB |
| **B. Full platform** (Oracle / AWS / Azure / GCP / DigitalOcean) | `fseg` `fsi` `med` `fabi` `flsh` `superadmin` | **Same** MySQL DB for all |

Never expose the `template/` folder as a public website.

## Step 2 — Install production dependencies

From each folder you will upload:

```bash
cd fseg
composer install --no-dev --optimize-autoloader
```

If the host has no Composer, you **must** upload the `vendor/` directory too.

Repeat for every instance folder in option B.

## Step 3 — Decide public URLs

Write the final HTTPS URLs (examples):

- `https://fseg.example.edu/`
- `https://admin.example.edu/`

You will put them in `app.baseURL` and (for superadmin) `app.previewBase.*`.

## Step 4 — Do **not** upload your local `.env`

Local files contain demo passwords and `CI_ENVIRONMENT = development`.

On the server, create `.env` from:

- [env.production.faculty.example](env.production.faculty.example)
- [env.production.superadmin.example](env.production.superadmin.example)

Minimum production truths:

```ini
CI_ENVIRONMENT = production
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
database.default.DBDebug = false
app.requireKnownHostname = true
```

Then on the server:

```bash
php spark key:generate
```

## Step 5 — What to upload / what to skip

**Upload**

- `app/`, `public/`, `vendor/`, `writable/` (empty structure OK), `spark`, `composer.json`, `composer.lock`
- `deploy/nginx/uploads-security.conf` (for VPS Nginx)

**Do not upload**

- Local `.env` / `LOCAL_CREDENTIALS.md`
- `writable/cache/*`, `writable/logs/*`, `writable/session/*` contents
- `tests/` (optional omit)
- Another site’s uploads if you only deploy one faculty

## Step 6 — Database strategy

1. Create empty MySQL/MariaDB DB (`utf8mb4`) on the host.
2. Prefer migrations on the server:

```bash
php spark migrate --all
```

3. Create production admins with **new** passwords (see `LOCAL_CREDENTIALS.md` for *command shapes* only — never reuse those passwords online).
4. Importing a local dump is riskier (demo users, local URLs). If you do it, rotate every password afterward.

## Step 7 — After files are on the server

1. Finish the chosen host guide (`01`–`08`)
2. Complete [09-production-go-live.md](09-production-go-live.md)
3. Run:

```bash
php spark app:production-check
php spark app:production-check --strict
```

## Definition of “prepared”

- [ ] `composer install --no-dev --optimize-autoloader` done
- [ ] No local `.env` in the upload package
- [ ] URLs and instance roles decided
- [ ] You know you must pass checklist `09` before calling it production
