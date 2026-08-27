# Commencer ici

Ce dépôt est le **modèle (template)** de la plateforme : une application
CodeIgniter 4 complète que l'on copie pour créer soit un site facultaire
(site public + administration dans le même dossier), soit l'instance
superadministration. Ce n'est pas un squelette vide, et ce n'est pas un site
en production : les instances réelles sont des copies configurées de ce dossier.

## Lecture rapide

1. Lire [README.md](README.md) pour la vue d'ensemble technique.
2. Lire [docs/TEMPLATE_REBUILD_PLAN.md](docs/TEMPLATE_REBUILD_PLAN.md) pour
   l'architecture cible et les décisions prises.
3. Lire [docs/MULTI_FOLDER_DEPLOYMENT.md](docs/MULTI_FOLDER_DEPLOYMENT.md)
   pour créer une instance (faculté ou superadmin).
4. Lire [docs/README.md](docs/README.md) pour choisir le bon guide.

## Lancement local minimal

```bash
composer install
cp .env.example .env
php spark key:generate
php spark migrate --all
php spark admin:create-superadmin
php spark serve
```

L'application publique est disponible sur `http://localhost:8080/`.
L'administration est disponible sur `/admin`.

## Rappels

- Le public est en français par défaut et peut afficher l'anglais.
- L'authentification et l'administration restent en français.
- Le serveur web doit exposer uniquement `public/`.
- Ne jamais copier un `.env` réel d'une instance vers une autre ; partir
  toujours de `.env.example`.
- Les tests peuvent recréer la base configurée comme `database.tests.database`;
  lire [tests/README.md](tests/README.md) avant de les lancer.
