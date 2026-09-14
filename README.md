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

```bash
# Depuis n’importe quelle instance (ex. fseg) — les six sites si présents :
./scripts/dev-serve.sh start
```

Ou dans un dossier : `./scripts/dev-serve.sh` (lit `app.baseURL` du `.env`).

## Documentation

- Canonique : [`template/docs/`](template/docs/) — index [`template/docs/README.md`](template/docs/README.md)
- Opérations locales (seed, cartes, admins) : [`template/docs/LOCAL_OPERATIONS.md`](template/docs/LOCAL_OPERATIONS.md)
- Créer une faculté : [`template/docs/CREER_UN_SITE.md`](template/docs/CREER_UN_SITE.md)
- Multi-dossiers : [`template/docs/MULTI_FOLDER_DEPLOYMENT.md`](template/docs/MULTI_FOLDER_DEPLOYMENT.md)

## Scripts racine (`scripts/`)

| Script | Rôle |
|---|---|
| `seed-ub-faculty-content.php` | Contenu réaliste UB pour les cinq facultés |
| `seed-ub-faculty-data-fseg-fsi.php` | Payloads FSEG/FSI (inclus par le seeder) |
| `update-faculty-maps.php` | Iframes Google Maps + adresses campus |
| `ensure-faculty-admins.php` | Créer / réinitialiser les admins locaux |

## Identifiants locaux

Les mots de passe et comptes de connexion sont dans **`LOCAL_CREDENTIALS.md`**
à la racine. Ce fichier est **ignoré par Git** (voir `.gitignore`). Créez-le
à partir de vos `.env` et des commandes admin CLI si besoin.

## Synchroniser le code

```bash
cd template && ./scripts/sync-instances.sh
```

Recopie `app/`, `tests/` et `public/assets/` vers fseg, fsi, superadmin, med,
fabi et flsh. Ne touche pas aux `.env`, `docs/` ni README d’instance.
