> **Document D'Origine** — conservé comme référence des besoins et critères
> d'acceptation exprimés par le client. L'état actuel du système est décrit
> dans [ARCHITECTURE.md](ARCHITECTURE.md).

# Cahier Des Charges - Plateforme Web Multi-Facultes UB

Derniere actualisation documentaire : 16 aout 2026.

Ce cahier des charges formalise les besoins et criteres d'acceptation de la plateforme FSEG/multi-facultes presente dans ce repository. Il complete les guides techniques existants et doit etre relu avec le client avant mise en production.

## 1. Contexte Et Objectifs

La plateforme doit fournir un site institutionnel administrable pour la Faculte des Sciences Economiques et de Gestion et pouvoir servir d'autres facultes de l'Universite du Burundi avec le meme code applicatif.

Objectifs principaux :

- publier les informations officielles de la faculte : presentation, formations, recherche, personnel, actualites, alumni et contact;
- permettre une administration autonome par faculte, avec isolation stricte des contenus;
- proposer un affichage public en francais et en anglais, avec le francais comme source;
- centraliser la gestion technique des sites facultaires par des superadministrateurs;
- assurer une base securisee, maintenable et deployable sur un hebergement PHP/MySQL.

## 2. Parties Prenantes

| Acteur | Role attendu |
|---|---|
| Visiteur public | Consulter les pages, rechercher les actualites, envoyer un message de contact. |
| Administrateur facultaire | Gerer les contenus de sa faculte et traiter les messages. |
| Editeur | Mettre a jour certains contenus selon ses permissions. |
| Superadministrateur | Gerer les sites facultaires, utilisateurs, permissions et parametres globaux. |
| Equipe technique | Installer, migrer, sauvegarder, surveiller et corriger la plateforme. |
| Client/representant UB | Valider les contenus, domaines, workflows et criteres de recette. |

## 3. Perimetre Fonctionnel

### Site Public

- Accueil dynamique avec hero, carrousel, atouts, statistiques, formations, recherche, personnel, actualites et blocs de contenu.
- Page Faculte avec presentation, historique et mot du doyen.
- Pages Formations avec liste par niveau et detail par formation.
- Page Recherche avec laboratoires, publications, projets et statistiques.
- Page Corps enseignant avec liste et detail des membres.
- Actualites et evenements avec publication, programmation, detail, recherche et filtre de type.
- Page Alumni avec profils, temoignages et statistiques.
- Formulaire de contact avec validation serveur, anti-spam simple, stockage en base et notification email facultative.
- Changement de langue public francais/anglais.

### Administration

- Authentification avec CodeIgniter Shield.
- Tableau de bord facultaire avec indicateurs reels.
- Tableau de bord central reserve aux superadministrateurs.
- CRUD admin pour contenus d'accueil, carrousel, atouts, statistiques, programmes, personnel, laboratoires, publications, projets, historique, pages, blocs, alumni, temoignages, parametres, sites et messages.
- Gestion des actualites/evenements avec brouillon, programmation, publication et archivage.
- Gestion des utilisateurs avec protection contre l'auto-verrouillage et la desactivation du dernier superadministrateur.
- Selection du site actif dans l'administration lorsque l'utilisateur a acces a plusieurs sites.

## 4. Exigences Multi-Sites

- Les sites facultaires sont stockes dans `sites`.
- Les affectations administrateurs-sites sont stockees dans `user_sites`.
- Tous les contenus dependants d'une faculte doivent porter `site_id`.
- Les lectures de contenu doivent utiliser le scoping du site actif.
- Les slugs, cles et parametres uniques doivent etre uniques par site, pas globalement.
- Chaque faculte est servie par sa propre copie du modele (dossier dedie), liee a son site via la configuration ; aucun repli vers une autre faculte.
- L'administration centrale doit rester limitee aux superadministrateurs.
- Un administrateur facultaire ne doit jamais voir ni modifier les contenus d'une faculte non affectee.

## 5. Exigences Bilingues

- Le francais est la langue source et la langue par defaut.
- L'administration et l'authentification restent en francais.
- Le public peut afficher le francais ou l'anglais.
- Les traductions editoriales anglaises sont stockees dans `content_translations`.
- Lorsqu'une traduction manque, le champ francais correspondant doit s'afficher.
- Les routes publiques restent en francais pour garder une structure stable.

## 6. Exigences Non Fonctionnelles

### Securite

- CSRF active globalement.
- Headers securises actives.
- CSP activee en production.
- Routes explicites; auto-routage historique desactive.
- Uploads limites aux images JPG, PNG et WebP, taille maximale 2 Mo.
- `public/uploads/` doit interdire l'execution de scripts.
- Le serveur web doit exposer uniquement `public/`.
- Les secrets doivent rester dans `.env` ou variables serveur, jamais dans Git.

### Exploitation

- PHP 8.2 ou plus recent.
- MySQL/MariaDB avec InnoDB et `utf8mb4`.
- Composer disponible au moins lors de la preparation du deploiement.
- Migrations executables par `php spark migrate --all`.
- Healthcheck disponible via `/healthz`.
- Sauvegardes regulieres de la base et de `public/uploads/`.

### Performance Et Maintenance

- Les controleurs doivent rester legers.
- La logique de lecture et d'assemblage doit rester dans les services ou modeles.
- Les tests DB doivent s'executer sequentiellement sur une base dediee.
- Les documents d'installation, securite et recette doivent rester synchronises avec le code.

## 7. Contraintes Techniques

![Architecture globale](diagrams/architecture_globale.png)

- Stack : CodeIgniter 4.7.x, Shield 1.3.x, PHP 8.2+, Bootstrap 5, PHPUnit 10.
- Dossier public : `public/`.
- Dossier d'ecriture : `writable/`.
- Dossier media public : `public/uploads/sites/{slug}/...`.
- Reference visuelle : `reference-static-site/`, a ne pas modifier sans demande explicite.

## 8. Criteres D'Acceptation

- Les pages publiques principales repondent en 200 avec contenu coherent.
- `/admin` redirige les visiteurs non connectes vers la connexion.
- Les permissions admin bloquent les utilisateurs non autorises.
- Un site facultaire ne peut lire que ses propres contenus.
- Une traduction anglaise absente retombe sur le francais.
- Le formulaire contact valide les donnees et stocke le message.
- Les uploads invalides ou executables sont rejetes.
- `composer validate`, `php spark routes`, `php spark migrate:status` et les tests pertinents sont executables.
- `php spark app:production-check` ne doit plus contenir d'erreur bloquante avant livraison.

## 9. Recette Client

La recette doit couvrir :

- navigation publique desktop et mobile;
- creation, modification, publication et suppression logique de contenus;
- creation de site facultaire et verification des hostnames;
- affectation d'utilisateurs a une faculte;
- saisie et affichage de traductions anglaises;
- envoi et traitement de messages de contact;
- verification des contenus definitifs, images officielles et coordonnees;
- verification de la configuration production : HTTPS, base, SMTP, sauvegardes et domaines.

## 10. Hors Perimetre Actuel

- Workflow editorial avance avec validation multi-etapes.
- Associations obligatoires entre programmes et personnel.
- Associations obligatoires entre projets et laboratoires.
- Application mobile native.
- Paiement en ligne ou integrations externes avancees.
