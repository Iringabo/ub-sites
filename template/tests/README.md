# Tests

Le projet utilise PHPUnit 10 avec les outils de test CodeIgniter 4.

## Avertissement Base De Test

`tests/bootstrap.php` supprime puis recrée la base configurée dans `database.tests.database` à chaque processus PHPUnit lorsque `ENVIRONMENT=testing` et que le pilote est `MySQLi`.

Ne pointez jamais `database.tests.database` vers une base de développement, recette ou production. La base de test doit être dédiée, par exemple `platform_test` (exemple suivi par Git) ou un autre nom local dédié tel que `ub_shared_test`, avec un utilisateur SQL limité à cet usage. Ne jamais utiliser `ub_shared`.

## Commandes

```bash
php spark test
php spark testdb:migrate
php spark testdb:seed TemplateStarterSeeder
```

`php spark test` est la commande principale. Les commandes `testdb:*` existent pour préparer explicitement la connexion `tests`.

## Configuration

- `phpunit.dist.xml` charge `tests/bootstrap.php`.
- La base par défaut dans `phpunit.dist.xml` est `platform_test` (surcharge possible via `.env`).
- Les variables `database.tests.*` peuvent être surchargées dans `.env` ou l'environnement système.
- Le préfixe de test configuré est `test_`.

## Types De Tests Présents

- Tests base de données : schéma, seeders et indépendance des modules.
- Tests feature : routes publiques, administration (menu en quatre sections, catégories de texte `admin/textes/*` et bandeaux, paramètres découpés, rôles administrateur/éditeur facultaires, sélecteur superadmin, session, onboarding), messages, contenus, sécurité, multi-site.
- Tests unitaires : helpers, médias, notifications, santé, production readiness, nom du cookie de session, responsive/accessibilité, palette par faculté (`SiteThemeServiceTest` : dérivation des nuances, contraste AA, couleur secondaire, accent ; `AccessibilityResponsiveTest` vérifie qu'aucune couleur de marque n'est codée en dur dans `style.css` hors `:root`).

## Bonnes Pratiques

- Vérifier que la base de test est distincte avant chaque exécution complète.
- Lancer les tests qui utilisent `DatabaseTestTrait` de manière séquentielle sur une même base; des processus PHPUnit parallèles peuvent se concurrencer pendant la suppression/recréation de `database.tests.database`.
- Ne jamais utiliser `migrate:fresh` sur la base de développement.
- Ajouter un test lorsqu'une route, une permission, une migration ou un comportement multi-site change.
- Les données de démonstration utilisées par les tests proviennent de `TemplateStarterSeeder`.
