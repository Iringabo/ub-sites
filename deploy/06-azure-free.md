# 06 — Microsoft Azure Free (12 months + always-free services)

**Cost:** New accounts typically get **12 months** of free popular services + some always-free quotas; often includes starting credits  
**Type:** Cloud VM (Linux)  
**Fits:** Full multi-faculty platform on one Ubuntu VM  

Start: https://azure.microsoft.com/free/

## Important

- Confirm the **current** free offer on Microsoft’s Free page (credits and 12‑month services change)
- Payment method usually required
- Create a budget alert on day one

## Step 1 — Activate Azure Free

1. Go to https://azure.microsoft.com/free/
2. Sign in with a Microsoft account
3. Complete verification
4. Open **Cost Management + Billing** → set a budget alert

## Step 2 — Create a free-eligible Linux VM

1. Portal → **Virtual machines** → **Create**
2. Image: **Ubuntu 22.04/24.04 LTS**
3. Size: pick a Free Tier / free-eligible small B-series size if offered for your subscription
4. Authentication: SSH public key
5. Open ports: **22, 80, 443** (or use NSG rules)
6. Deploy and note the public IP

If the portal pushes you to a paid SKU, stop and filter for free-eligible sizes, or use your free credits consciously.

## Step 3 — Connect and install LAMP/LEMP

```bash
ssh azureuser@PUBLIC_IP
```

Install Nginx + PHP 8.3 + MySQL + Composer (same package list as
[01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md)).

## Step 4 — Shared database

```sql
CREATE DATABASE ub_shared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ubapp'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON ub_shared.* TO 'ubapp'@'localhost';
FLUSH PRIVILEGES;
```

## Step 5 — Upload instances

Deploy folders under `/var/www/` (`fseg`, `fsi`, …, `superadmin`).

Configure `.env` files with Azure public hostname or your custom domains.

## Step 6 — Nginx + HTTPS

- One server block per faculty, `root /var/www/<slug>/public;`
- Install Certbot for Let’s Encrypt

## Step 7 — Migrate and create users

```bash
cd /var/www/fseg
php spark migrate --all
```

Create production admins (never reuse local demo passwords).

## Step 8 — Verify

- [ ] HTTPS works
- [ ] Faculty isolation OK (`app.siteSlug`)
- [ ] `writable/` and uploads OK
- [ ] Budget alert tested

## After 12 months

Stop or resize the VM, migrate to Oracle Always Free, or accept paid billing.

## Production gate (required)

Apply production `.env` templates, include Nginx uploads security, run `php spark app:production-check --strict`, then complete [09-production-go-live.md](09-production-go-live.md).
