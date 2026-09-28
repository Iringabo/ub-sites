# 05 — AWS Free Tier (12 months)

**Cost:** Free Tier for **12 months** for many new accounts (then paid unless you stop resources)  
**Type:** Cloud VPS (EC2) + optional free-tier RDS  
**Fits:** Full platform on one Ubuntu EC2  

Start: https://aws.amazon.com/free/

## Important

- Free Tier is **time-limited** (typically 12 months from account creation for classic free-tier EC2 hours)
- Set **billing alarms** immediately so you are not surprised after the year
- Prefer a single small EC2 (`t2.micro` / `t3.micro` where still Free Tier eligible in your region)

## Step 1 — Create an AWS account

1. Sign up at https://aws.amazon.com/
2. Add a payment method (required)
3. Open **Billing → Budgets** and create an alert at $1 / $5

## Step 2 — Launch an Ubuntu EC2 instance

1. EC2 → **Launch instance**
2. Name: `ub-platform`
3. AMI: **Ubuntu Server 22.04/24.04 LTS**
4. Instance type: Free Tier eligible (e.g. `t3.micro` / `t2.micro` if listed)
5. Key pair: create/download `.pem`
6. Security group inbound rules:
   - SSH 22 from your IP
   - HTTP 80 from anywhere
   - HTTPS 443 from anywhere
7. Storage: stay within Free Tier GB if possible
8. Launch and note the **public DNS / IP**

## Step 3 — SSH and install the stack

```bash
chmod 400 your-key.pem
ssh -i your-key.pem ubuntu@EC2_PUBLIC_DNS
```

Install Nginx, PHP 8.3, MySQL, Composer — same commands as in
[01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md) (Step 4).

## Step 4 — Database

Either:

- **A.** MySQL on the same EC2 (simplest, free-tier friendly), or
- **B.** Amazon RDS MySQL Free Tier (extra moving parts; watch storage hours)

For A, create `ub_shared` as in the Oracle guide.

## Step 5 — Deploy application folders

Upload `fseg`, `fsi`, `med`, `fabi`, `flsh`, `superadmin` to `/var/www/`.

```bash
cd /var/www/fseg && composer install --no-dev --optimize-autoloader
```

Configure each `.env` with production URLs and the shared DB.

## Step 6 — Reverse proxy / vhosts

Configure Nginx server blocks with `root .../public;` for each subdomain, then Certbot.

## Step 7 — Migrate + admins

```bash
cd /var/www/fseg
php spark migrate --all
# create faculty / superadmin users with strong passwords
```

## Step 8 — Verify and calendar reminder

- [ ] All faculty URLs load on HTTPS
- [ ] Calendar reminder **1 month before Free Tier ends**
- [ ] Plan to stop/delete the instance or accept billing

## Cost control

- Stop unused instances
- Delete unused Elastic IPs (they can cost money when unattached)
- Watch data transfer out of AWS

## Production gate (required)

Include `deploy/nginx/uploads-security.conf` in each Nginx site, apply `env.production.*.example`, then finish [09-production-go-live.md](09-production-go-live.md) (`php spark app:production-check --strict`).