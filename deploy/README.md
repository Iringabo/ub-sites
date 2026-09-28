# Deploy guides (free or long free trial)

Step-by-step guides to put the **Université du Burundi multi-faculty** platform
(CodeIgniter 4 + shared MySQL) on the internet **without paying**, or with a
**free trial longer than 2 weeks**.

Each provider has its **own file**. Start with preparation, then pick one host,
then finish with the **production go-live checklist**.

## Production-ready path (required order)

1. [00-prepare-locally.md](00-prepare-locally.md) — build artifacts, secrets rules  
2. One host guide (`01` … `08`) — put the code online  
3. Apply [env.production.faculty.example](env.production.faculty.example) / [env.production.superadmin.example](env.production.superadmin.example)  
4. [09-production-go-live.md](09-production-go-live.md) — **mandatory** before users  
5. On every instance: `php spark app:production-check` (then `--strict`)

A host guide alone is **not** production-ready. Checklist `09` is the gate.

## Files in this folder

| File | Type | Duration | Best for |
|------|------|----------|----------|
| [00-prepare-locally.md](00-prepare-locally.md) | Prep | — | Do this first |
| [01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md) | Always free VPS | **Forever** | Full platform (all faculties + superadmin) |
| [02-infinityfree.md](02-infinityfree.md) | Free shared PHP | **Forever** | One faculty demo / learning |
| [03-alwaysdata-free.md](03-alwaysdata-free.md) | Free managed PHP | **Forever** | All 6 sites, one shared database, `*.alwaysdata.net` |
| [04-awardspace-free.md](04-awardspace-free.md) | Free shared PHP | **Forever** | Simple single-site tryout |
| [05-aws-free-tier.md](05-aws-free-tier.md) | Cloud trial | **12 months** | Learning AWS + one solid VPS |
| [06-azure-free.md](06-azure-free.md) | Cloud trial | **12 months** (+ some always-free) | Learning Azure |
| [07-google-cloud-free.md](07-google-cloud-free.md) | Cloud credit | **90 days** ($300 credit) | Temporary full stack |
| [08-digitalocean-trial.md](08-digitalocean-trial.md) | VPS credit | Often **60 days** ($200) | Clean Ubuntu droplet |
| [09-production-go-live.md](09-production-go-live.md) | **Go-live gate** | — | Security, HTTPS, checks, smoke tests |
| [env.production.faculty.example](env.production.faculty.example) | Template | — | Faculty `.env` |
| [env.production.superadmin.example](env.production.superadmin.example) | Template | — | Superadmin `.env` |
| [nginx/uploads-security.conf](nginx/uploads-security.conf) | Nginx snippet | — | Block PHP execution under `/uploads` |

## What this project needs

- **PHP 8.2+** (8.3 recommended) with `intl`, `mbstring`, `mysqli`, `curl`, `gd`, `fileinfo`, `json`, `dom`
- **MySQL 8** or MariaDB (one **shared** database for all faculty folders)
- Document root pointing to each instance’s **`public/`** folder
- Several folders if you want the full platform: `fseg`, `fsi`, `med`, `fabi`, `flsh`, `superadmin`
- Production flags: `CI_ENVIRONMENT=production`, `DBDebug=false`, HTTPS forced, unique `encryption.key` per instance

Project docs (deeper detail):

- `template/docs/MULTI_FOLDER_DEPLOYMENT.md`
- `template/docs/GUIDE_INSTALLATION_DEPLOIEMENT.md`
- `template/docs/GUIDE_DEPLOIEMENT_CPANEL.md`
- `template/docs/SECURITY_REVIEW.md`

## Quick recommendation

1. **Complete multi-faculty platform, free long term** → [01-oracle-cloud-always-free.md](01-oracle-cloud-always-free.md) then [09](09-production-go-live.md)
2. **Six sites on one shared database, free, `*.alwaysdata.net` only** → [03](03-alwaysdata-free.md) then [09](09-production-go-live.md). One faculty on a simpler panel → [02](02-infinityfree.md)
3. **Cloud trial with credit** → AWS / Azure / Google / DigitalOcean then [09](09-production-go-live.md)

## Not included (on purpose)

Hosts with **only ≤ 14 days** free trial, or no PHP/MySQL support, are omitted.

## Disclaimer

Free plans change. Always read the provider’s current terms (commercial use,
custom domains, inactivity rules). These guides are operational runbooks for
**this repository**; still verify panel labels when a provider redesigns its UI.
