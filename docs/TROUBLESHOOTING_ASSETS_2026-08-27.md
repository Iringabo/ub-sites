# Troubleshooting Guide — Static assets fail to load under `php spark serve` (CodeIgniter 4)

Applicable to the FSEG instance (and sibling folders), but generic to any CI4 app.
Reference facts for this project:
- `.env` (project root): `app.baseURL = 'http://localhost:8101/'`
- `app/Config/App.php:19`: `public string $baseURL = 'http://localhost:8080/';` (default fallback)
- `app/Config/App.php:175`: `public bool $forceGlobalSecureRequests = false;`
- Views reference assets as `<?= base_url('assets/css/style.css') ?>` (see `app/Views/layouts/admin.php`, `app/Views/auth/login.php`, error pages).
- `public/.htaccess` exists (Apache rewrite rules) — **Apache-only, ignored by `php spark serve`**.
- Static files live in `public/assets/{css,js,images,vendor}`.

---

## 0. Symptom → root cause

"Renders as plain HTML" means the browser got the page markup but **not** the
linked CSS/JS/images. There are exactly two failure modes:

- **A. Wrong `baseURL`** → the `<link>`/`src` URLs point to the wrong host or
  port → the browser's request for the asset never reaches the dev server
  (network failure / wrong port).
- **B. Asset file missing or unreadable** → the request (correct URL) 404s →
  the front controller (`public/index.php`) returns an HTML page instead of
  the file → page looks unstyled.

`php spark serve` serves existing files in `public/` **directly**; only missing
files fall through to `index.php`. So an asset that returns HTML is, in practice,
a missing/unreadable file (mode B) or a URL pointing elsewhere (mode A).

---

## 1. Configuration Verification (`baseURL`)

The single most common cause is a `baseURL` that does **not** match the host/port
you serve on.

1. **Find both definitions.**
   - `.env`: `app.baseURL = 'http://localhost:8101/'`
   - `app/Config/App.php:19`: `public string $baseURL = 'http://localhost:8080/';`
   `.env` **overrides** `App.php`. If `.env` is missing/typo'd/unreadable, CI4
   silently falls back to `http://localhost:8080/`.
2. **Confirm which URL the browser actually uses.** View page source and look
   at a generated tag, e.g.:
   ```
   <link href="http://localhost:8101/assets/css/style.css" rel="stylesheet">
   ```
   That exact `http://localhost:8101/...` is what the browser requests. If it
   shows `:8080` (or another host), `baseURL` is wrong.
3. **Synchronize port + host.** The port in `app.baseURL` MUST equal the
   `--port` passed to `php spark serve`. Mismatch → assets requested on the
   wrong port → mode A.
4. **Scheme must match how you serve.** For local dev use `http://`, not
   `https://`, unless you truly serve HTTPS.
5. **Check `forceGlobalSecureRequests`.** If `true` while you serve plain
   `http://`, CI4 rewrites *every* URL (including asset links) to `https://` →
   mixed-content / redirect loop → assets blocked. Keep it `false` locally
   (`app/Config/App.php:175` and `.env` `app.forceGlobalSecureRequests = false`).
6. **Trailing slash.** `baseURL` must end with `/` (`http://localhost:8101/`).
7. **Fix & restart.** Edit `.env` (preferred) so `app.baseURL` exactly equals
   `http://<host>:<port>/`, then restart the server:
   ```
   php spark cache:clear        # if view/config caching is on
   # stop the running server (Ctrl-C) and:
   php spark serve --host localhost --port 8101
   ```
8. **Verify:**
   ```
   curl -s http://localhost:8101/ | grep -o 'href="[^"]*style.css"'
   # should print the URL on :8101, not :8080
   ```

---

## 2. Path Implementation in views (using `base_url()`)

Always generate asset URLs with the helper so they inherit `baseURL`.

- **Correct:** `<?= base_url('assets/css/style.css') ?>` →
  `<baseURL>assets/css/style.css`. No leading slash, no hardcoded domain.
- **Wrong — hardcoded leading slash:** `href="/assets/style.css"`. Resolves
  against the server root; breaks if the app lives in a subpath or the
  `baseURL` host differs from the request host.
- **Wrong — relative, no helper:** `href="assets/style.css"`. Resolves
  relative to the *current route URL* (e.g. `/admin/users`), so it 404s on
  subpages.
- **Wrong — `site_url()` for assets:** `site_url()` is for routes and may
  include `index.php` depending on config. Use `base_url()` for static files.
- **Best practice:** never hardcode the domain; route all assets through
  `base_url('assets/...')`. After any change, confirm the rendered markup's
  asset URLs match the `php spark serve` origin.

---

## 3. Server Execution (`php spark serve`)

`php spark serve` automatically uses `public/` as the document root (it serves
`public/index.php` as the router). You normally do **not** need `-t public`.

1. **Run from the project root** (e.g. `fseg/`), never from inside `public/`.
2. **Match the port to `baseURL`:**
   ```
   php spark serve --host localhost --port 8101
   ```
3. **If you used `php -S` manually instead:** the docroot defaults to the
   current directory. If you weren't in `public/`, assets won't resolve.
   Either use `php spark serve`, or:
   ```
   php -S localhost:8101 -t public
   ```
4. **Understand the routing model.** The PHP built-in server serves existing
   static files natively; only requests for files that don't exist are passed
   to `index.php`. Therefore an asset that returns HTML is almost always a
   missing file (mode B) or a wrong URL (mode A) — not a rewrite problem.
5. **Verify the server is actually serving the file:**
   ```
   curl -I http://localhost:8101/assets/css/style.css
   # Expect: HTTP/1.1 200 + Content-Type: text/css
   curl -I http://localhost:8101/assets/js/admin.js
   # Expect: HTTP/1.1 200 + Content-Type: application/javascript
   ```
   Any `404` here means the file isn't where the URL points → go to §4.

---

## 4. File Integrity & Permissions

### 4.1 Verify the asset files exist
```
ls -l public/assets/css/style.css \
      public/assets/js/admin.js \
      public/assets/js/main.js \
      public/assets/images/logo-placeholder.png
```
If a file is absent, that request returns HTML (mode B). Restore it from the
`template/` instance or your VCS.

### 4.2 Third-party assets (e.g. Bootstrap)
```
ls public/assets/vendor/bootstrap/            # bootstrap CSS/JS
ls public/assets/vendor/bootstrap-icons/      # icon font
```
`assets/vendor` may be a **symlink** created by
`scripts/new-faculty-instance.sh --link-vendor` (points at the source
instance's `vendor`). If that symlink target is missing or traversal is blocked
by permissions, assets 403. Check:
```
readlink public/assets/vendor
ls -ld public/assets/vendor "$(readlink -f public/assets/vendor)"
```

### 4.3 Fix permissions
Files need at least `644` (`rw-r--r--`), directories `755` (`rwxr-xr-x`), and
the user running `php spark serve` must be able to read them:
```
find public/assets -type d -exec chmod 755 {} \;
find public/assets -type f -exec chmod 644 {} \;
# If served under a web-server user, ensure group/owner readability:
chown -R "$(id -un)":www-data public/assets 2>/dev/null || true
chmod -R u+rwX,go+rX public/assets
```

### 4.4 Ownership / symlink traversal
If assets return `403 Forbidden`, the directory tree above `public/assets`
(include `public/` itself) must be executable (`o+x`) so the server can descend
into it. A `chmod 700 public` or a broken symlink target will produce exactly
this symptom.

After fixing, re-run the `curl -I` checks from §3.

---

## 5. Environment-Specific Debugging

### 5.1 `.htaccess` is Apache-only
`php spark serve` (PHP built-in server) **does not read `.htaccess`**. So:
- A broken `.htaccess` will **not** affect `spark serve`, but **will** affect an
  Apache deployment. The correct `.htaccess` must live in **`public/`**, not
  the project root. A root-level `.htaccess` is irrelevant for CI4 (the front
  controller is `public/index.php`).
- For later Apache deployment, confirm `DocumentRoot` points to `public/`,
  `AllowOverride All`, and `RewriteBase` (if the app is in a subfolder).

### 5.2 Routing interference
A route that captures `/assets/*` (e.g.
`$routes->get('assets/(:any)', ...)`) or a catch-all can short-circuit asset
delivery under Apache (where every request is routed to `index.php` first).
Under `spark serve` existing files are served directly, so this is less likely,
but audit `app/Config/Routes.php` to ensure no route shadows `assets/*`.

### 5.3 Known built-in PHP server behaviors
- **Single-threaded:** concurrent asset requests can queue/slow under load
  (appears as intermittent failures), though not 404s.
- **Ignores `.htaccess`/`.user.ini` PHP directives.**
- **Only serves files inside the docroot (`public/`).** Paths outside `public/`
  (e.g. `writable/`, `app/`) are intentionally not web-accessible.
- Watch the server log: `[200]: GET /assets/css/style.css` = served;
  `[404]` = file not where expected.

### 5.4 `forceGlobalSecureRequests`
If `true`, all generated URLs (including `base_url()` asset links) become
`https://`. Serving `http://localhost` then yields mixed-content or redirect
loops → assets blocked. Keep `false` for local `spark serve` (§1 step 5).

### 5.5 CSP / headers
This app emits a strict CSP (`default-src 'self'`). Local `base_url()` assets
are same-origin → allowed. If you later load a CDN font/script, add it to the
CSP or it will be blocked (not the cause for local assets, but a common
"assets blocked" pitfall in other setups).

### 5.6 `.env` not loaded
If `app.baseURL` is missing/typo'd in `.env`, CI4 falls back to
`App.php:19` → `http://localhost:8080/`. Ensure `.env` exists at the project
root, is readable, has no syntax errors, and sets `app.baseURL` correctly.

### 5.7 Browser cache
After fixes, hard-reload (Ctrl+Shift+R) or disable cache; stale HTML may still
reference old broken URLs.

---

## 6. End-to-end verification checklist
```
curl -I http://localhost:8101/assets/css/style.css   # 200, text/css
curl -I http://localhost:8101/assets/js/admin.js     # 200, application/javascript
curl -s http://localhost:8101/ | grep -o 'assets/css/style.css'  # full correct URL
```
Browser DevTools → Network: no red/failed asset requests; CSS/JS show `200`.
View Source: every asset URL uses `http://localhost:8101/assets/...` (same
origin as the page).

---

## 7. Quick decision tree
- Asset request **404 / returns HTML** → file missing or wrong path → §2, §4.
- Asset requested on **wrong port/host** → `baseURL` mismatch → §1, §3.
- **Mixed content / redirect loop** → `forceGlobalSecureRequests` → §1, §5.4.
- Works on Apache but **not** `spark serve` (or vice-versa) → `.htaccess` /
  docroot mismatch → §5.1.
- **403 Forbidden** → permissions/ownership/symlink → §4.3–§4.4.
