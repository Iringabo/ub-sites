# Guide administrateur

Dernière actualisation : 25 septembre 2026.

Ce guide décrit le back-office tel qu’il fonctionne aujourd’hui. L’interface
est en français. Les textes anglais du site public se saisissent dans
l’onglet « Version anglaise » lorsqu’il est proposé.

## Menu admin (architecture actuelle)

Zones : **Accueil** (héros / sections & ordre / chiffres / textes des
sections), **Pages du site**, **Messages**, **Comptes**, **Identité**, et
**Plateforme** (superadmin uniquement). Les modules « Pages
institutionnelles », « Blocs de page » et « Points forts » sont retirés de
l’UI.

Sur l’accueil, chaque slide du héros porte badge, titre, texte et boutons
(lien via liste déroulante). L’ordre et la visibilité des sections se
gèrent dans **Sections & ordre** (glisser-déposer). Le voile du carrousel
est fixe (30 %) — plus de réglage admin.

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

Le menu n’est pas une copie du site public. Il est regroupé par métier. Une
zone sans droit disparaît.

### Tableau de bord

Synthèse du site actif, messages récents, et une liste de mise en route tant
que des textes d’exemple (« à remplacer ») n’ont pas été écrasés. Les
raccourcis respectent les permissions.

### Accueil

Héros (slides), sections & ordre, chiffres clés, textes des sections.

### Pages du site

Actualités et événements, formations, présentation / mot du doyen,
historique, personnel, alumni, témoignages, laboratoires, publications,
projets.

Pour peupler localement une faculté « comme terminée » (contenu + images
isolées par site) : depuis `superadmin/`, `php spark site:seed-demo --force`
(voir `docs/LOCAL_OPERATIONS.md`).

Pour l’aperçu sur l’accueil : cochez **Mis en avant sur l’accueil** (et
éventuellement **Ordre sur l’accueil**) sur les formations, laboratoires et
membres du personnel concernés. Pour les actualités / événements, utilisez
**Mettre en avant sur l’accueil**. L’accueil affiche au plus quatre personnes
et trois actualités/événements mis en avant.

Les projets et laboratoires sont indépendants. Les formations ne sont pas
liées obligatoirement au personnel. Un témoignage peut, ou non, pointer vers
un profil alumni.

### Messages

**Messages de contact** : filtrer, ouvrir, marquer lu / traité, archiver,
supprimer (suppression logique ; pas d’écran de restauration).

### Comptes & Identité

Visible pour l’administrateur de faculté et le superadministrateur.

- **Coordonnées et identité** (`/admin/settings/global`) : textes publics,
  coordonnées, liens, images. Jamais de secrets techniques.
- **Comptes & accès** (dossier facultaire) : comptes de **cette** faculté.
  L’administrateur de faculté ne peut y créer que des **éditeurs**.

Sur l’instance superadmin, le groupe **Plateforme** s’ajoute : aperçu du
site choisi, liste des facultés, comptes de toutes les facultés (y compris
création d’administrateurs de faculté).

## Qui peut faire quoi

| Rôle | Où | Droits |
|---|---|---|
| Éditeur | `/admin` de sa faculté | Accueil + Pages du site. Pas de messages, pas de comptes, pas d’identité. |
| Administrateur de faculté | `/admin` de sa faculté | Accueil, Pages du site, Messages, Comptes, Identité. Crée uniquement un **éditeur** pour *sa* faculté. Pas d’autre administrateur, pas de superadmin, pas d’autre site. |
| Superadministrateur | dossier `superadmin/` | Plateforme + les zones de la faculté **choisie**. Seul rôle autorisé à **créer des administrateurs** de faculté. |

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
- côté faculté : uniquement le rôle **éditeur de cette faculté** ;
- côté superadmin : rôle **administrateur** ou **éditeur** de la faculté choisie.

Côté faculté, le formulaire crée toujours un éditeur (seul un
superadministrateur peut créer un administrateur). Côté superadmin, on
choisit le groupe plateforme puis la faculté (pré-cochée = faculté active)
et le rôle sur cette faculté.

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
