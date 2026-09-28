# 07 — Google Cloud Free ($300 credit / ~90 days)

**Cost:** New accounts typically receive about **$300 credit for 90 days** (more than 2 weeks) plus a limited Always Free layer  
**Type:** Compute Engine VM  
**Fits:** Temporary full-platform demo  

Start: https://cloud.google.com/free

## Important

- The **90-day credit** ends; set billing alerts
- Always Free GCE `e2-micro` exists in some regions but is tight for many PHP workers — credits make a small `e2-small` easier during the trial
- Payment method usually required

## Step 1 — Create a GCP account / billing account

1. Open https://cloud.google.com/free
2. Activate the Free Trial
3. Create a project: `ub-platform`
4. Billing → Budgets & alerts (e.g. alert at 50% of credit)

## Step 2 — Create a Compute Engine VM

1. **Compute Engine** → **VM instances** → **Create**
2. Region: choose one close to your users
3. Machine type: small (use credits) or Always Free `e2-micro` if you accept low performance
4. Boot disk: Ubuntu 22.04/24.04
5. Firewall: allow HTTP / HTTPS
6. Create; note external IP

## Step 3 — SSH from browser or local

```bash
gcloud compute ssh ub-platform --project=YOUR_PROJECT --zone=YOUR_ZONE
```

Or use the SSH button in the console.

## Step 4 — Install stack

Install Nginx, PHP 8.3 extensions, MySQL, Composer (commands identical to the Oracle guide Step 4).

## Step 5 — Database + code

1. Create `ub_shared` + user
2. Upload faculty + superadmin folders to `/var/www/`
3. `composer install --no-dev --optimize-autoloader` in each
4. Write production `.env` files

## Step 6 — Domains and TLS

- Point DNS A records to the VM IP (or use the ephemeral IP for a quick test)
- Nginx vhosts → each `public/`
- `certbot --nginx`

## Step 7 — Migrate / seed / verify

```bash
cd /var/www/fseg
php spark migrate --all
```

Optional: `php spark site:seed-demo --force` from superadmin (uses disk; fine during trial).

Checklist:

- [ ] All faculty sites load
- [ ] Admin login works
- [ ] Budget alert active
- [ ] Calendar reminder before day 90

## Exit plan before credits end

Export a SQL dump + `public/uploads`, then move to
[01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md) or a paid host.

## Production gate (required)

Same as other VPS guides: production `.env`, uploads Nginx snippet, `app:production-check --strict`, checklist [09-production-go-live.md](09-production-go-live.md).
