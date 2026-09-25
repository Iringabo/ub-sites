# Plateforme multi-facultés — Université du Burundi

Plusieurs dossiers CodeIgniter 4 partagent **une seule base** (`ub_shared`).
Le code source de vérité est [`template/`](template/). Les instances live
sont des copies configurées par `.env`.

## Dossiers

| Dossier | Rôle | Port local |
|---|---|---|
| [`template/`](template/) | Modèle (jamais exposé au web) | — |
| [`fseg/`](fseg/) | Faculté des Sciences Économiques et de Gestion | 8101 |
| [`fsi/`](fsi/) | Faculté des Sciences et Ingénierie | 8102 |
| [`superadmin/`](superadmin/) | Superadministration (`centralAdminMode`) | 8103 |
| [`med/`](med/) | Faculté de Médecine | 8104 |
| [`fabi/`](fabi/) | Faculté d’Agronomie et de Bioingénierie | 8105 |
| [`flsh/`](flsh/) | Faculté des Lettres et des Sciences Humaines | 8106 |

## Démarrage local

Le script `dev-serve` vit **dans chaque instance** (pas à la racine du dépôt).
Depuis une instance, il démarre par défaut les six sites présents :

```bash
cd fseg
./scripts/dev-serve.sh start
./scripts/dev-serve.sh status   # chaque /healthz → 204
```

Sous-ensemble : `PLATFORM_INSTANCES='fseg fsi' ./scripts/dev-serve.sh start`.

Utiliser `http://localhost:PORT` (pas `127.0.0.1`) pour que les cookies
correspondent à `app.baseURL`.

## Documentation

- Canonique : [`template/docs/`](template/docs/) — index [`template/docs/README.md`](template/docs/README.md)
- Opérations locales (seed, cartes, admins, serve) : [`template/docs/LOCAL_OPERATIONS.md`](template/docs/LOCAL_OPERATIONS.md)
- Créer une faculté : [`template/docs/CREER_UN_SITE.md`](template/docs/CREER_UN_SITE.md)
- Multi-dossiers : [`template/docs/MULTI_FOLDER_DEPLOYMENT.md`](template/docs/MULTI_FOLDER_DEPLOYMENT.md)
- Mise en ligne (hébergeurs gratuits / essais longs) : [`deploy/README.md`](deploy/README.md) — checklist production [`deploy/09-production-go-live.md`](deploy/09-production-go-live.md)

## Scripts

| Emplacement | Rôle |
|---|---|
| `scripts/seed-ub-faculty-content.php` | Contenu réaliste UB pour les cinq facultés (legacy) |
| `scripts/seed-ub-faculty-data-fseg-fsi.php` | Payloads FSEG/FSI (inclus par le seeder) |
| `scripts/update-faculty-maps.php` | Iframes Google Maps + adresses campus |
| `scripts/ensure-faculty-admins.php` | Créer / réinitialiser les admins locaux |
| `php spark site:seed-demo --force` (depuis `superadmin/`) | **Recommandé** : démo contenu + médias par site |
| `template/scripts/new-faculty-instance.sh` | Copie guidée d’une faculté |
| `template/scripts/new-admin-instance.sh` | Copie guidée de la superadministration |

Détail seed / médias : [`template/docs/LOCAL_OPERATIONS.md`](template/docs/LOCAL_OPERATIONS.md).

## Synchroniser le code

```bash
cd template && ./scripts/sync-instances.sh
```

Recopie `app/`, `tests/` et `public/assets/` vers fseg, fsi, superadmin, med,
fabi et flsh. Ne touche pas aux `.env`, `docs/` ni README d’instance.
