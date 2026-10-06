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
- Tests feature : routes publiques, administration (menu en quatre sections, catégories de texte `admin/textes/*` et bandeaux, paramètres découpés, rôles administrateur/éditeur facultaires, sélecteur superadmin, session, onboarding), messages, contenus, sécurité, multi-site. `FacultyInformationPagesTest` couvre les pages ajoutées pour le public (admissions, départements groupés par cycle et leur isolation par site, FAQ, mentions légales, fichier `.ics` par événement, 404 des anciennes pages agenda et partenaires), le bandeau de tous les partenaires sur La Faculté, les deux carrousels actualités/événements de l'accueil, l'auteur et la pièce jointe d'un article sans balises `<p>` visibles, la fiche formation enrichie (conditions d'accès), `sitemap.xml`, `robots.txt` dynamique, canonical/hreflang `?lang=`, JSON-LD, recherche élargie, filtres et pagination du personnel, CSP de la mesure d'audience, menus déroulants et pied de page (lien Administration), ainsi que l'organisation des études (troncs communs imbriqués TIC puis GI-GL/SE-RT avec leurs années « Bac I », « Bac II », « Bac III–IV » ; tronc partagé entre départements avec les pastilles « Partagé avec » ; étapes sur la fiche formation ; aucune option d'une autre faculté), la maquette PDF du département (proposée seulement si le fichier existe), l'absence de doctorat (liste et fiche en 404), l'encadré et le lien de pied de page vers le site des études doctorales (masqués tant que le réglage est vide ou non HTTPS) et la migration qui masque les anciens doctorats. `AdminUsersTest` vérifie que le superadmin ne crée que des administrateurs ou éditeurs de faculté (rôle et faculté obligatoires, rôle facultaire dérivé du groupe), que les comptes superadmin sont en lecture seule et qu'un compte supprimé peut être recréé avec le même nom et le même e-mail ; `AdminCliCreationTest` couvre `admin:create-superadmin` avec paramètres ; `AdminResourceCrudTest` refuse un tronc commun sans département, valide les champs « Parcours d’études » (durées sans tronc, tronc de filière sans premier tronc, troncs plus longs que la formation, niveau doctorat refusé) et n'accepte en maquette de département qu'un vrai PDF ; `DemoPageContentShapeTest` vérifie aussi les départements réels de la FSEG, leurs maquettes, la licence en 240 crédits et les cours communs du tronc. `PostManagementTest` vérifie aussi l'auteur prérempli, le texte enregistré sans balises HTML et le retrait de la pièce jointe.
- Tests unitaires : helpers, médias (dont validation des brochures PDF : extension, type MIME et signature `%PDF-`), notifications, santé, production readiness, nom du cookie de session, responsive/accessibilité, palette par faculté (`SiteThemeServiceTest` : dérivation des nuances, contraste AA, couleur secondaire, accent ; `AccessibilityResponsiveTest` vérifie qu'aucune couleur de marque n'est codée en dur dans `style.css` hors `:root` et que le parcours d'études a ses styles et des cibles tactiles suffisantes) ; `StudyPathServiceTest` couvre l'arbre des troncs (imbriqués, partagés entre départements, ignorés s'ils ne concernent qu'une formation) et les libellés d'années.

## Bonnes Pratiques

- Vérifier que la base de test est distincte avant chaque exécution complète.
- La suite complète dépasse vingt minutes : pendant le développement, lancer un fichier à la fois (`php vendor/bin/phpunit --configuration phpunit.dist.xml --no-logging tests/feature/FacultyInformationPagesTest.php`).
- Lancer les tests qui utilisent `DatabaseTestTrait` de manière séquentielle sur une même base; des processus PHPUnit parallèles peuvent se concurrencer pendant la suppression/recréation de `database.tests.database`.
- Ne jamais utiliser `migrate:fresh` sur la base de développement.
- Ajouter un test lorsqu'une route, une permission, une migration ou un comportement multi-site change.
- Les données de démonstration utilisées par les tests proviennent de `TemplateStarterSeeder`.
