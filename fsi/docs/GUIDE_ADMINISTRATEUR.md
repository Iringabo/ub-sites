# Guide Administrateur

Dernière actualisation documentaire : 11 août 2026.

Ce guide décrit les écrans réellement présents dans le back-office.

## Connexion

Aller sur `/admin`. Si vous n'êtes pas connecté, l'application affiche la page de connexion en français. Utiliser l'adresse email et le mot de passe fournis par l'administrateur technique.

## Tableau De Bord

Le tableau de bord affiche une synthèse des contenus du site actif : publications, formations, messages, contenus récents et raccourcis de gestion.

## Choisir Le Site Facultaire

Si votre compte a accès à plusieurs sites, un sélecteur apparaît dans l'administration. Le site choisi détermine les contenus visibles et modifiables.

Un superadministrateur peut accéder à tous les sites actifs. Un autre administrateur ne voit que les sites qui lui sont affectés.

## Accueil

La zone Accueil permet de gérer :

- contenu d'accueil;
- images du carrousel;
- atouts;
- statistiques.

Les textes français sont saisis dans les champs principaux. Les traductions anglaises se saisissent dans la section "Version anglaise" lorsqu'elle est disponible.

## Actualités Et Événements

Le module permet de créer, modifier, filtrer et publier des actualités ou événements.

Statuts disponibles :

- brouillon;
- programmé;
- publié;
- archivé.

Les actions de publication, retour en brouillon, programmation et archivage sont effectuées depuis l'administration. Un événement peut avoir des dates, un lieu et un lien d'inscription.

## Formations

Le module Formations gère les programmes de licence, master et doctorat. Chaque formation possède un slug, une durée, un résumé, une description, des conditions d'admission, des débouchés et des options de mise en avant sur l'accueil.

Les formations ne sont pas liées obligatoirement au personnel.

## Recherche

La zone Recherche contient :

- laboratoires;
- publications scientifiques;
- projets de recherche.

Les projets et laboratoires sont indépendants. Il n'existe pas de relation obligatoire entre eux dans l'application actuelle.

## Personnel

Le module Personnel gère les enseignants-chercheurs et le personnel administratif : nom, photo, grade, spécialité, rôle, email, biographie, publication et ordre d'affichage.

## Alumni

La zone Communauté contient :

- profils alumni;
- témoignages.

Un témoignage peut être lié facultativement à un profil alumni.

## Pages Et Historique

Les pages publiques modifiables sont prédéfinies. Vous pouvez modifier leurs contenus et métadonnées, mais la création et la suppression de pages sont désactivées dans l'interface actuelle.

Le module Historique gère les repères chronologiques affichés sur la page faculté.

## Faculté

Le menu Faculté contient l'écran "Présentation / Mot du doyen". Il permet de modifier, pour le site facultaire actif :

- la photo du doyen;
- le nom complet;
- la fonction;
- le domaine ou département;
- le petit libellé;
- le titre principal;
- le message;
- la signature affichée en bas du message.

Les champs français sont la version source. L'onglet English permet de saisir les textes anglais. Les champs anglais laissés vides utilisent la version française sur le site public.

La photo est commune aux deux langues. Si aucune nouvelle image n'est choisie, la photo actuelle est conservée. Si une nouvelle image est envoyée, seuls les formats JPG, PNG et WebP sont acceptés.

## Messages De Contact

Le module `/admin/messages` permet de consulter les messages envoyés depuis le formulaire public.

Actions disponibles :

- filtrer par texte, statut et dates;
- ouvrir un message;
- marquer comme lu;
- marquer comme traité;
- archiver;
- supprimer.

La suppression retire le message de l'interface courante après archivage et suppression logique. Il n'existe pas d'écran de restauration des messages dans l'administration actuelle.

## Utilisateurs Et Permissions

Le module Utilisateurs permet, selon vos droits :

- créer un utilisateur;
- modifier son nom, email, état actif/inactif;
- réinitialiser un mot de passe;
- affecter groupes, permissions et sites;
- activer ou désactiver un compte.

Les protections empêchent de retirer son propre accès admin et de désactiver le dernier superadministrateur.

## Sites Facultaires

Le module Sites facultaires est réservé à `sites.manage`. Il permet de gérer l'identifiant, le nom, le slug, les domaines autorisés, l'état, la langue par défaut, le logo, les couleurs et les coordonnées.

La suppression des sites est désactivée.

## Paramètres Publics

Les paramètres publics modifiables alimentent les textes, coordonnées, liens et images du site. Les secrets techniques ne doivent jamais y être stockés.

## Langue

Le public peut choisir français ou anglais. L'administration reste en français. Les champs anglais laissés vides utilisent le français sur le site public.

## Déconnexion

Utiliser le bouton de déconnexion du back-office. Après déconnexion, les pages `/admin` demandent de se reconnecter.
