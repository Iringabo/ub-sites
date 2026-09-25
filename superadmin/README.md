# Superadministration — Université du Burundi

Cette instance sert à **piloter les facultés déjà existantes** : liste,
comptes, et édition du contenu de la faculté choisie. Elle partage la même
base que les dossiers facultaires.

Ce dossier n’est pas un modèle et ne crée pas de nouveau site.

## Connexion

```bash
composer install
cp .env.example .env
php spark key:generate
```

Dans `.env` :

- `app.baseURL` — en local : `http://localhost:8103/`
- `app.centralAdminMode = true`
- `session.cookieName = ci_session_central`
- `database.default.*` — la **même** base que les dossiers facultaires (fseg, fsi, med, fabi, flsh)

Puis :

```bash
./scripts/dev-serve.sh
```

- Administration : `http://localhost:8103/admin`
- La page d’accueil publique redirige vers `/admin`

Connectez-vous avec un compte du groupe **superadministrateur**.

Identifiants locaux : `LOCAL_CREDENTIALS.md` à la racine de la plateforme (fichier ignoré par Git).

## Choisir une faculté

1. Le tableau de bord plateforme liste les facultés (pas le modèle interne).
2. **Gérer le contenu** ou le sélecteur en haut enregistre la faculté active.
3. Les zones Accueil / Pages du site / Messages / Identité portent alors sur
   **cette** faculté : les enregistrements écrivent son `site_id`.
4. **Voir le site** ouvre l’adresse publique de la faculté choisie, dans un
   nouvel onglet.

Les comptes de toutes les facultés se gèrent depuis **Comptes**. On y crée
le premier administrateur d’une faculté ; celui-ci crée ensuite ses éditeurs
depuis le `/admin` de sa faculté.

On ne crée pas de faculté depuis cet écran. Pour configurer une faculté
déjà présente (nom, domaines, statut) : **Facultés** → Modifier.

## Tests

```bash
php spark test
```

Lire [tests/README.md](tests/README.md) : la base de test est recréée.

## Documentation

- [Guide administrateur](docs/GUIDE_ADMINISTRATEUR.md)
- [Index](docs/README.md)
