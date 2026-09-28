# 01 — Oracle Cloud Always Free (recommended for full platform)

**Cost:** Always Free (does not expire if you stay within Always Free limits)  
**Type:** Your own Ubuntu VPS  
**Fits:** All faculties + superadmin on one server  

Official Always Free overview:  
https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm

## Why this option

You get a real Linux server (often **Ampere A1**: up to 2 OCPU / 12 GB RAM Always Free).  
That is enough to run Apache/Nginx + PHP 8.3 + MySQL and every faculty folder.

## Limits / risks

- Capacity shortages in some regions (“out of host capacity”) — try another AD or wait
- You manage security updates yourself
- Credit card may be required at signup (Oracle still offers Always Free resources)

## Step 1 — Create an Oracle Cloud account

1. Go to https://www.oracle.com/cloud/free/
2. Sign up for **Free Tier**
3. Complete identity verification
4. Note your **home region** (Always Free compute must be there)

## Step 2 — Create a free Ubuntu VM

1. Console → **Compute** → **Instances** → **Create instance**
2. Name: `ub-platform`
3. Image: **Ubuntu 22.04** or **24.04**
4. Shape: prefer **VM.Standard.A1.Flex** (Ampere), Always Free eligible  
   - Example: **2 OCPU**, **12 GB** RAM (or two smaller VMs)
5. Networking: create/use a VCN with a public subnet + public IP
6. Add your **SSH public key**
7. Create the instance and wait until it is **Running**
8. Copy the **public IP**

## Step 3 — Open firewall ports (OCI + Ubuntu)

### A. OCI Security List / Network Security Group

Allow ingress:

| Port | Purpose |
|------|---------|
| 22 | SSH |
| 80 | HTTP |
| 443 | HTTPS |

Do **not** open MySQL `3306` to the internet.

### B. On the VM (after SSH)

```bash
ssh ubuntu@YOUR_PUBLIC_IP

sudo apt update && sudo apt upgrade -y
sudo apt install -y ufw
sudo ufw allow OpenSSH
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

Oracle images sometimes also need `iptables` rules for 80/443 — if HTTP fails after Apache install, check OCI docs for Ubuntu Free Tier Apache guide.

## Step 4 — Install PHP, Composer, MySQL, Nginx (or Apache)

Example with **Nginx + PHP-FPM 8.3** on Ubuntu:

```bash
sudo apt install -y nginx mysql-server unzip git curl
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-intl php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-fileinfo

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## Step 5 — Create the shared database

```bash
sudo mysql
```

```sql
CREATE DATABASE ub_shared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ubapp'@'localhost' IDENTIFIED BY 'CHOOSE_A_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON ub_shared.* TO 'ubapp'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Step 6 — Upload the platform

On the server:

```bash
sudo mkdir -p /var/www
sudo chown -R $USER:www-data /var/www
cd /var/www
```

Copy from your PC (example):

```bash
# from your PC
rsync -avz --exclude '.env' --exclude 'writable/cache/*' --exclude 'writable/logs/*' \
  fseg fsi med fabi flsh superadmin ubuntu@YOUR_PUBLIC_IP:/var/www/
```

Or use Git clone of your private repo.

On each instance folder:

```bash
cd /var/www/fseg
composer install --no-dev --optimize-autoloader
```

## Step 7 — Configure each `.env`

Do **not** copy laptop `.env` files. Use:

- `deploy/env.production.faculty.example`
- `deploy/env.production.superadmin.example`

Create `/var/www/fseg/.env` (and similarly for others). Shared DB credentials are identical; only slug/cookies/URL change.

On each instance:

```bash
cd /var/www/fseg
php spark key:generate
```

Faculty essentials (`fseg`):

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://fseg.YOURDOMAIN/'
app.forceGlobalSecureRequests = true
app.CSPEnabled = true
app.siteSlug = fseg
app.centralAdminMode = false
app.requireKnownHostname = true
app.allowedHostnames = fseg.YOURDOMAIN
session.cookieName = ci_session_fseg
session.rememberCookieName = remember_fseg
database.default.hostname = localhost
database.default.database = ub_shared
database.default.username = ubapp
database.default.password = CHOOSE_A_STRONG_PASSWORD
database.default.DBDriver = MySQLi
database.default.DBDebug = false
```

Superadmin essentials:

```ini
app.baseURL = 'https://admin.YOURDOMAIN/'
app.centralAdminMode = true
app.requireKnownHostname = true
app.allowedHostnames = admin.YOURDOMAIN
session.cookieName = ci_session_central
session.rememberCookieName = remember_central
app.uploadMirrors = fseg:/var/www/fseg,fsi:/var/www/fsi,med:/var/www/med,fabi:/var/www/fabi,flsh:/var/www/flsh
app.previewBase.fseg = https://fseg.YOURDOMAIN/
```

Full templates live in the `deploy/` folder at the repo root.

## Step 8 — Nginx site for one faculty (repeat per domain)

Copy the uploads security snippet onto the server (from this repo):

```bash
sudo mkdir -p /etc/nginx/snippets
sudo cp /var/www/fseg/deploy/nginx/uploads-security.conf /etc/nginx/snippets/ub-uploads-security.conf
# or from the platform deploy folder you uploaded:
# sudo cp /path/to/deploy/nginx/uploads-security.conf /etc/nginx/snippets/ub-uploads-security.conf
```

`/etc/nginx/sites-available/fseg`:

```nginx
server {
    listen 80;
    server_name fseg.YOURDOMAIN;
    root /var/www/fseg/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    include snippets/ub-uploads-security.conf;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* /\. {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/fseg /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Point DNS A records of each subdomain to the VM public IP.

## Step 9 — HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d fseg.YOURDOMAIN -d fsi.YOURDOMAIN -d admin.YOURDOMAIN
```

## Step 10 — Permissions

```bash
sudo chown -R www-data:www-data /var/www/fseg/writable /var/www/fseg/public/uploads
sudo find /var/www/fseg/writable -type d -exec chmod 775 {} \;
```

Repeat for other instances.

## Step 11 — Migrate and create admins

Run migrations **once** (any instance):

```bash
cd /var/www/fseg
php spark migrate --all
```

Create faculty site rows / admins using your project commands (see `LOCAL_CREDENTIALS.md` patterns, but with **new production passwords**):

```bash
PLATFORM_ADMIN_PASSWORD='StrongProdPass!' php spark admin:create-faculty-admin \
  --email admin@fseg.example --username fsegadmin --password-env PLATFORM_ADMIN_PASSWORD
```

Optional demo content (from superadmin, after sites exist):

```bash
cd /var/www/superadmin
php spark site:seed-demo --force
```

## Step 12 — Production gate (required)

```bash
cd /var/www/fseg
php spark app:production-check
php spark app:production-check --strict
```

Repeat on every instance. Then complete the full checklist:

→ [09-production-go-live.md](09-production-go-live.md)

## Step 13 — Verify

- [ ] `https://fseg.YOURDOMAIN/` loads
- [ ] Each faculty shows its own content (`app.siteSlug`)
- [ ] Superadmin `/admin` works; public `/` redirects to admin
- [ ] Uploads and writable folders work
- [ ] `production-check` reports no blocking errors

## Keep it free

Stay on Always Free shapes and storage limits. Monitor the Oracle billing dashboard so you do not accidentally create paid resources.
