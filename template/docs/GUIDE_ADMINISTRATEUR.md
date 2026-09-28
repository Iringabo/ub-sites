# Guide administrateur

Dernière actualisation : 27 septembre 2026.

Ce guide décrit le back-office tel qu’il fonctionne aujourd’hui. L’interface
est en français. Les textes anglais du site public se saisissent dans
l’onglet « English » lorsqu’il est proposé.

## Menu admin (architecture actuelle)

Le menu suit ce que voit le visiteur, en quatre sections, de la plus
utilisée à la plus rare :

1. **Au quotidien** : Tableau de bord, **Messages reçus** (la pastille
   indique le nombre de messages non lus).
2. **Pages du site** : un menu déroulant par page publique, dans l’ordre du
   menu du site (Accueil, La Faculté, Formations, Recherche, Corps
   enseignant, Actualités & événements, Alumni, Contact). L’icône
   ↗ à côté du nom ouvre la page publique. Chaque menu contient :
   - **Sections de la page** : de petits formulaires (2 à 6 champs), un par
     bloc visible, du haut vers le bas de la page. Enregistrer une section ne
     modifie que ses propres champs.
   - **Listes** : les éléments que l’on ajoute ou retire (programmes,
     laboratoires, personnel, articles, témoignages…). **Chiffres clés**
     reste sous Accueil.
3. **Paramètres du site** (affichés sur toutes les pages) : Identité & logo,
   Coordonnées, Réseaux sociaux, Pied de page, Référencement & partage.
   Chacun est une petite page (`/admin/settings/identity`, `contact`,
   `social`, `footer`, `seo`).
4. **Administration** : Comptes & rôles.

Sur l’instance centrale, la section **Plateforme** vient en premier ; les
sections de la faculté n’apparaissent qu’après avoir choisi une faculté.
Les modules « Pages institutionnelles », « Blocs de page », « Points forts »
et « Textes des sections » sont retirés de l’UI.

### Trouver un texte : Ctrl+K

**Ctrl+K** (ou le bouton **Rechercher**) ouvre une recherche sur tout le
menu : nom de la section, nom de la page et libellés des champs. Les accents
sont facultatifs. Exemples : « doyen » → La Faculté › Mot du doyen ;
« carte » → Contact › Carte ; « témoignages » ; « téléphone » → Paramètres
du site › Coordonnées. **Entrée** ouvre le premier résultat.

### Bandeau de chaque page

Chaque page publique (sauf l’accueil, qui a son carrousel) a une section
**Bandeau** : image de fond (large, 1600 px minimum ; commune FR/EN), titre
et sous-titre (FR/EN). Titre vide = nom standard de la page. Le fil
d’Ariane « Accueil / Page » n’est pas modifiable.

Sur l’accueil, chaque image du **Carrousel d’images** porte badge, titre,
texte et boutons (lien via liste déroulante) ; la taille des pastilles se
règle sur cette même page. L’ordre et la visibilité des blocs se gèrent dans
**Ordre des blocs** (glisser-déposer). Le voile du carrousel est fixe
(30 %) — plus de réglage admin.

Sur l’instance centrale, choisissez une faculté dans le sélecteur : le
contenu se recharge pour cette faculté (jamais un formulaire d’édition
d’un autre site). Les domaines autorisés ne s’éditent pas depuis
superadmin ; les couleurs se choisissent avec un sélecteur de couleur.

## Connexion

Aller sur `/admin`. Utiliser l’adresse e-mail et le mot de passe fournis.

- Une faculté : se connecter sur le `/admin` **de son dossier** (exemples
  locaux : FSEG `http://localhost:8101/admin`, FSI `:8102`, MED `:8104`,
  FABI `:8105`, FLSH `:8106`).
- Superadministrateur : se connecter sur le dossier `superadmin/`
  (`http://localhost:8103/admin`). Seul le groupe Shield `superadmin` y est
  accepté.

Consulter une page publique **ne déconnecte pas**. La session se termine
uniquement avec **Déconnexion**.

La case **Se souvenir de moi** (30 jours) n’est proposée que sur les
dossiers facultaires. Ne pas l’utiliser sur un ordinateur partagé : elle
reconstruit la session sans mot de passe. L’instance superadmin la
désactive.

**Voir le site** ouvre le site public dans un nouvel onglet.

## Zones de travail

Une entrée sans droit disparaît ; une section vide aussi.

### Tableau de bord

Synthèse du site actif, messages récents, et une liste de mise en route tant
que des textes d’exemple (« à remplacer ») n’ont pas été écrasés. Les
**actions rapides** (Publier une actualité, Ajouter un événement, Voir les
messages non lus) respectent les permissions.

### Pages du site

Une page = un menu déroulant. Exemple pour La Faculté : Bandeau, Mot du
doyen (avec photo), Mission & vision, Valeurs, Historique, puis la liste
**Frise chronologique**. Quand une information vit ailleurs (adresse,
téléphone), le formulaire affiche un lien vers le bon écran.

Sur l’accueil et sur `/actualites` (« Tous »), actualités et événements
sont mélangés dans un seul fil ; la recherche couvre les deux types et
affiche « Aucun résultat ne correspond à votre recherche. » si rien ne
correspond.

Pour peupler localement une faculté « comme terminée » (contenu + images
isolées par site) : depuis `superadmin/`, `php spark site:seed-demo --force`
(voir `docs/LOCAL_OPERATIONS.md`). Le seed applique aussi la couleur par
défaut de chaque faculté (FSEG émeraude, FSI vert forêt, FABI vert feuille,
MED cramoisi, FLSH bordeaux).

**Sections & ordre** (Accueil) : la section **Accès rapides** affiche quatre
raccourcis fixes (Formations, Recherche, Actualités & événements, Contact)
sous le carrousel ; elle se masque ou se déplace comme les autres. L'ordre
recommandé est carrousel → accès rapides → chiffres clés → présentation →
formations → actualités → recherche → mot du doyen → équipe → mot d'accueil
→ contact.

**Couleur de la faculté** : le superadministrateur choisit la couleur
principale (et, au besoin, la couleur foncée) dans la fiche du site
(Plateforme › Facultés). Toutes les nuances du site public (boutons, titres,
fonds pâles, pied de page) en découlent automatiquement ; choisissez une
nuance du vert ou du rouge du logo de l'Université du Burundi. Les boutons et
textes colorés sont assombris si nécessaire pour rester lisibles.

Pour l’aperçu sur l’accueil : cochez **Mis en avant sur l’accueil** (et
éventuellement **Ordre sur l’accueil**) sur les formations, laboratoires et
membres du personnel concernés. Pour les actualités / événements, utilisez
**Mettre en avant sur l’accueil**. L’accueil affiche au plus quatre personnes
et trois actualités/événements mis en avant.

Les projets et laboratoires sont indépendants. Les formations ne sont pas
liées obligatoirement au personnel. Un témoignage peut, ou non, pointer vers
un profil alumni.

### Messages reçus

Filtrer, ouvrir, marquer lu / traité, archiver, supprimer (suppression
logique ; pas d’écran de restauration).

### Paramètres du site et Administration

Visible pour l’administrateur de faculté et le superadministrateur.

- **Paramètres du site** : cinq petites pages (identité & logo,
  coordonnées, réseaux sociaux, pied de page, référencement & partage).
  Enregistrer une page ne modifie que ses champs. Jamais de secrets
  techniques. L’ancienne adresse `/admin/settings/global` redirige vers
  Identité & logo.
- **Comptes & rôles** (dossier facultaire) : comptes de **cette** faculté.
  L’administrateur de faculté y crée un **administrateur** ou un
  **éditeur** de sa faculté.

Sur l’instance superadmin, le groupe **Plateforme** s’ajoute : aperçu du
site choisi, liste des facultés, comptes de toutes les facultés (y compris
création d’administrateurs de faculté).

## Qui peut faire quoi

| Rôle | Où | Droits |
|---|---|---|
| Éditeur | `/admin` de sa faculté | Tableau de bord + Pages du site. Pas de messages, pas de paramètres, pas de comptes. |
| Administrateur de faculté | `/admin` de sa faculté | Les quatre sections. Crée un **administrateur** ou un **éditeur** pour *sa* faculté. Pas de superadmin, pas d’autre site. |
| Superadministrateur | dossier `superadmin/` | Plateforme + les sections de la faculté **choisie**. |

Le personnel d’une faculté ne peut pas administrer une autre faculté, même
en tapant une autre URL.


## Choisir une faculté (superadmin)

1. Le tableau de bord liste les facultés **réelles** (pas le modèle interne
   `template` / `demo`).
2. **Gérer le contenu** ou le sélecteur en haut enregistre la faculté active.
3. L’édition se fait **ici**, sans ouvrir le dossier de la faculté.
4. **Facultés** permet de modifier nom, domaines, statut, couleurs. On ne
   **crée** pas une faculté depuis cet écran : copier le dossier `template/`
   (voir `template/docs/CREER_UN_SITE.md`).

## Comptes & accès

Selon les droits :

- créer un compte, modifier nom / e-mail / actif ;
- réinitialiser un mot de passe ;
- côté faculté : rôle **administrateur** ou **éditeur de cette faculté** ;
- côté superadmin : rôle **administrateur** ou **éditeur** de la faculté choisie.

Côté faculté, le formulaire propose deux cartes : Administrateur (gère
aussi messages, paramètres et comptes) ou Éditeur (contenus uniquement).
Côté superadmin, on choisit le groupe plateforme puis la faculté
(pré-cochée = faculté active) et le rôle sur cette faculté.

L’écran facultaire propose un seul site (le sien) et un rôle, pas une grille
de permissions. On ne peut pas se retirer soi-même l’accès admin ni
désactiver le dernier superadministrateur.

## Contenus : règles communes

- **Actualités / événements** : brouillon, programmé, publié, archivé.
- **Pages** : pages prédéfinies ; création et suppression désactivées.
- **Mot du doyen** : photo commune aux deux langues (JPG, PNG, WebP).
- Les champs anglais vides affichent le français sur le site public.

## Langue

Le public peut choisir français ou anglais (cookie `site_locale_{slug}`).
L’administration reste en français.

## Déconnexion

Bouton **Déconnexion** du back-office. Ensuite, `/admin` redemande une
connexion.
