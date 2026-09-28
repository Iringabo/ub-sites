# 08 — DigitalOcean trial credit (often 60 days / $200)

**Cost:** New accounts frequently receive a **welcome credit** (commonly around **$200 for 60 days** — confirm the current promo; it is longer than 2 weeks when active)  
**Type:** Simple Ubuntu Droplet VPS  
**Fits:** Clean full-platform deploy while credit lasts  

Start: https://www.digitalocean.com/

## Important

- Promotions change; verify the banner/offer at signup
- After credit ends, droplets bill hourly — destroy what you do not need
- Very beginner-friendly UI compared to big clouds

## Step 1 — Create an account and claim credit

1. Open https://www.digitalocean.com/
2. Sign up
3. Check **Billing** for free credit balance and expiry date
4. Enable email billing notifications

## Step 2 — Create a Droplet

1. **Create** → **Droplets**
2. Region: close to you
3. Image: **Ubuntu 24.04 LTS**
4. Size: basic shared CPU (1 GB RAM minimum; 2 GB more comfortable for MySQL + PHP)
5. Authentication: SSH key
6. Hostname: `ub-platform`
7. Create Droplet → copy IPv4

## Step 3 — Secure SSH and update

```bash
ssh root@DROPLET_IP
apt update && apt upgrade -y
adduser deploy
usermod -aG sudo deploy
# optional: disable password SSH later
```

## Step 4 — Install Nginx, PHP 8.3, MySQL, Composer

Use the same package installation steps as
[01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md) (Step 4).

Enable firewall:

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
```

## Step 5 — Shared database

Create `ub_shared` and application user (utf8mb4).

## Step 6 — Deploy the multi-folder app

```bash
mkdir -p /var/www
# rsync/scp/git your instance folders here
cd /var/www/fseg && composer install --no-dev --optimize-autoloader
```

Configure `.env` for each instance (shared DB, different `app.siteSlug` / cookies / `app.baseURL`).

## Step 7 — Nginx server blocks

One file per faculty + superadmin, each with:

```nginx
root /var/www/fseg/public;
try_files $uri $uri/ /index.php?$query_string;
```

Reload Nginx, then:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d fseg.YOURDOMAIN -d admin.YOURDOMAIN
```

## Step 8 — Migrate, admins, optional demo seed

```bash
cd /var/www/fseg
php spark migrate --all
```

Create production admins. Optionally seed from `superadmin/`.

## Step 9 — Verify

- [ ] Faculty public sites
- [ ] Superadmin only `/admin`
- [ ] Uploads writable
- [ ] Credit balance still OK

## Before credit expires

Snapshot the droplet or export DB + uploads, then either:

- move to Oracle Always Free, or
- keep a small paid droplet if the project goes live for real.

## Production gate (required)

1. Copy [nginx/uploads-security.conf](nginx/uploads-security.conf) into each site  
2. Apply [env.production.faculty.example](env.production.faculty.example) / [env.production.superadmin.example](env.production.superadmin.example)  
3. `php spark app:production-check --strict` on every instance  
4. Finish [09-production-go-live.md](09-production-go-live.md)
