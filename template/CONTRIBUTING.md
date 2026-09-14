# Contribuer À La Plateforme Multi-Facultés

Ce dépôt est une application CodeIgniter 4 avancée. Les contributions doivent rester petites, relisibles et vérifiables.

## Installation Développeur

```bash
composer install
cp .env.example .env
php spark key:generate
php spark migrate --all
# (optionnel) php spark db:seed TemplateStarterSeeder — contenu de départ neutre
php spark admin:create-superadmin
php spark serve
```

Ne copiez pas le fichier `env` suivi par Git pour créer un environnement local. Utilisez toujours `.env.example`.

## Règles De Base

- Ne pas modifier `vendor/`.
- Ne pas versionner `.env`, secrets SMTP, mots de passe, clés de chiffrement ou dumps de base.
- Ne pas modifier le schéma hors migration.
- Ne jamais supprimer de données avec une route GET.
- Garder les contrôleurs légers et placer les requêtes dans les modèles ou services.

## Branches Et Git

- Travailler par branche ou par petit lot cohérent.
- Utiliser des commits descriptifs et non interactifs.
- Ne pas réécrire l'historique sans accord explicite.
- Préserver les modifications existantes d'autres contributeurs dans le working tree.

## Copies d’instances

Le code source de vérité est `template/app`, `template/tests` et
`template/public/assets`. Après une modification, recopier vers fseg, fsi
et superadmin (`rsync -a`, sans `.env`, `writable/` ni `docs/`). Ne pas
partager `vendor/` par symlink sans `composer dump-autoload` dans la copie.

## Multi-Site

- Tout contenu éditorial site-dépendant doit avoir un `site_id`.
- Les requêtes publiques et administratives doivent filtrer via `forSite()` lorsqu'elles touchent un modèle site-scopé.
- Les slugs et clés éditoriales doivent rester uniques par site, pas globalement.
- Les administrateurs non superadmin ne doivent accéder qu'aux sites présents dans `user_sites`.

## Français Et Anglais

- Le français est la langue source et la langue par défaut.
- L'anglais est disponible côté public via `content_translations`.
- L'administration, l'authentification, les erreurs et notifications back-office restent en français.
- Les textes réutilisables doivent aller dans `app/Language/fr/` et `app/Language/en/` lorsque c'est pertinent.

## Sécurité

- Valider toutes les entrées serveur.
- Échapper les données dans les vues avec `esc()` ou les helpers existants.
- Conserver CSRF, secure headers et CSP actifs.
- Téléverser uniquement des images validées par MIME, extension, taille et nom aléatoire.
- Stocker les médias publics sous `public/uploads/sites/{slug}/...`.

## Tests Et Vérifications

Selon la zone modifiée :

```bash
composer validate
php spark routes
php spark migrate:status
php spark test
```

Avant `php spark test`, lire [tests/README.md](tests/README.md) : la base `database.tests.database` est supprimée et recréée par le bootstrap.

## Checklist PR

- Migration et seeder ajoutés si le schéma ou les données de démo changent.
- Permissions Shield et routes explicites vérifiées.
- Traductions FR/EN ajoutées ou fallback documenté.
- Isolation multi-site vérifiée pour les contenus concernés.
- Tests pertinents exécutés et résultat noté.
- Documentation mise à jour si le comportement visible change.
