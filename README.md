# Plateforme multi-facultés — Université du Burundi

Plusieurs dossiers CodeIgniter 4 partagent **une seule base** (`ub_shared`).
Le code source de vérité est [`template/`](template/). Les instances sont
des copies configurées par leur propre `.env`.

## Dossiers

| Dossier | Rôle | Port local |
|---|---|---|
| [`template/`](template/) | Modèle (jamais exposé au web) | — |
| [`fseg/`](fseg/) | Faculté des Sciences Économiques et de Gestion | 8101 |
| [`fsi/`](fsi/) | Faculté des Sciences de l’Ingénieur | 8102 |
| [`superadmin/`](superadmin/) | Superadministration (`centralAdminMode`) | 8103 |
| [`med/`](med/) | Faculté de Médecine | 8104 |
| [`fabi/`](fabi/) | Faculté d’Agronomie et de Bioingénierie | 8105 |
| [`flsh/`](flsh/) | Faculté des Lettres et des Sciences Humaines | 8106 |

## Démarrage local

Le script `dev-serve` vit **dans chaque instance** :

```bash
cd fseg
./scripts/dev-serve.sh start
./scripts/dev-serve.sh status   # chaque /healthz répond 204
./scripts/dev-serve.sh stop
```

Utiliser `http://localhost:PORT` (pas `127.0.0.1`) pour que les cookies
correspondent à `app.baseURL`. Mots de passe locaux : `LOCAL_CREDENTIALS.md`
(ignoré par Git).

## Documentation

Toute la documentation est dans [`template/`](template/), en français :

| Document | Contenu |
|---|---|
| [`template/README.md`](template/README.md) | vue d’ensemble, rôles, index des guides |
| [`template/docs/CREER_UN_SITE.md`](template/docs/CREER_UN_SITE.md) | ouvrir une nouvelle faculté (copie, `.env`, commandes) |
| [`template/docs/AGENT_NOUVEAU_SITE.md`](template/docs/AGENT_NOUVEAU_SITE.md) | consignes pour un agent qui prépare le contenu d’une nouvelle faculté |
| [`template/docs/GUIDE_ADMINISTRATEUR.md`](template/docs/GUIDE_ADMINISTRATEUR.md) | utiliser l’administration |
| [`template/docs/ARCHITECTURE.md`](template/docs/ARCHITECTURE.md) | fonctionnement et schéma de la base |
| [`template/docs/DEPLOIEMENT.md`](template/docs/DEPLOIEMENT.md) | mise en ligne |
| [`template/docs/OPERATIONS_LOCALES.md`](template/docs/OPERATIONS_LOCALES.md) | démonstration, cartes, comptes, tests |
| [`template/docs/SECURITE_QUALITE.md`](template/docs/SECURITE_QUALITE.md) | sécurité, accessibilité, critères de livraison |
| [`template/AGENTS.md`](template/AGENTS.md) | règles de travail sur le code |
| [`deploy/README.md`](deploy/README.md) | hébergeurs gratuits et essais ; checklist [`deploy/09-production-go-live.md`](deploy/09-production-go-live.md) |

Les instances reçoivent seulement la documentation d’exploitation ; les
guides de création restent dans le modèle.

## Scripts

| Emplacement | Rôle |
|---|---|
| `php spark site:seed-demo --force` (depuis `superadmin/`) | contenu de démonstration complet par faculté, médias et anglais |
| `scripts/update-faculty-maps.php` | cartes Google Maps et adresses des campus |
| `scripts/ensure-faculty-admins.php` | créer ou réinitialiser les comptes locaux |
| `scripts/seed-ub-faculty-content.php`, `seed-ub-faculty-data-fseg-fsi.php` | ancienne méthode de remplissage (remplacée par `site:seed-demo`) |
| `template/scripts/new-faculty-instance.sh` | copie guidée d’une faculté |
| `template/scripts/new-admin-instance.sh` | copie guidée de la superadministration |

## Synchroniser le code

```bash
cd template && ./scripts/sync-instances.sh
```

Recopie `app/`, `tests/`, `public/assets/` et la documentation
d’exploitation vers fseg, fsi, superadmin, med, fabi et flsh. Ne touche ni
aux `.env`, ni aux médias déposés.
