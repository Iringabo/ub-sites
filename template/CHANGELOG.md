# Journal des changements

Ce fichier suit les changements visibles du projet. Les dates ne sont
ajoutées que lorsqu’elles sont vérifiables.

## [Non publié]

- Site public : palette par faculté dérivée de la couleur principale du site
  (`SiteThemeService`, variables `--brand*` émises par le layout ; contraste
  AA garanti pour les textes et boutons ; accent = l’autre couleur du logo
  UB). Couleurs démo : FSEG émeraude, FSI vert forêt, FABI vert feuille,
  MED cramoisi, FLSH bordeaux. Plus aucune couleur de marque en dur dans
  `style.css`.
- Accueil : nouvelle section **Accès rapides** (Formations, Recherche,
  Actualités & événements, Contact) juste sous le carrousel ; ordre par
  défaut hero → accès rapides → chiffres → présentation → formations →
  actualités → recherche → mot du doyen → équipe → mot d’accueil → contact.
  Cartes de formation par niveau avec badge, durée et nombre de filières ;
  blocs date pour les événements ; mot du doyen en citation ; appel à
  l’action contact avec adresse, horaires et téléphone.
- Pages publiques : onglets Formations avec compteurs, filtres du personnel
  avec compteurs, blocs date sur `/actualites`, témoignages alumni en
  citation, coordonnées de contact en cartes, bouton de recherche dans la
  barre de navigation, mention de l’université dans le pied de page.
  Typographie : corps 17 px, titres fluides, largeur de lecture 70 ch, cibles
  tactiles 44 px, hero 70 vh (60 vh sur tablette).
- Seed démo : badge hero « SIGLE · Université du Burundi », deux appels à
  l’action par diapositive, libellés de boutons plus explicites, bloc
  contact enrichi.
- Menu d’administration en quatre sections (Au quotidien, Pages du site,
  Paramètres du site, Administration ; Plateforme en tête sur superadmin).
  Un menu déroulant par page publique, dans l’ordre du site, avec « Sections
  de la page » et « Listes » ; pastille des messages non lus.
- Textes de page découpés en petites catégories (`PageTextCatalog`,
  `admin/textes/{page}/{section}`) : chaque formulaire n’enregistre que ses
  champs, en FR et EN. Remplace `admin/faculty/profile`,
  `admin/pages/{formations|contact}` et « Textes des sections » (anciennes
  adresses redirigées).
- Catégorie **Bandeau** sur chaque page publique sauf l’accueil : image de
  fond, titre (`banner_title`) et sous-titre ; le fil d’Ariane garde le nom
  de la page.
- Paramètres du site en cinq petites pages (`/admin/settings/identity`,
  `contact`, `social`, `footer`, `seo`) ; `settings/global` redirige ;
  l’enregistrement ne touche que la page courante.
- Recherche Ctrl+K : toutes les catégories et pages de paramètres, par nom,
  page et libellés de champs, sans tenir compte des accents.
- Tableau de bord : actions rapides (actualité, événement, messages non lus).
- L’administrateur de faculté peut de nouveau créer un **administrateur** ou
  un **éditeur** de sa faculté.
- Accueil et `/actualites` (« Tous ») : actualités et événements mélangés ;
  la recherche couvre les deux types avec un seul message « Aucun résultat
  ne correspond à votre recherche. » / « No results found. ».

- Contenu démo aligné sur le modèle legacy : `pages.content` (doyen, mission,
  formations, contact/carte), traductions EN, campus par faculté, codes projet
  et URLs de publication ; éditeurs admin pour présentation, textes Formations /
  Contact et réseaux sociaux.
- Cookie de langue public par instance (`site_locale_{slug}`).
- `X-Forwarded-Host` ignoré sans `app.proxyIPs` (admin et public).
- Échec visible des paramètres si l’enregistrement échoue ; 404 HTML pour
  toutes les pages manquantes ; instance facultaire en échec fermé si la
  base est indisponible.
- Scripts de copie : cookies session/remember, refus de `--link-vendor`,
  `sync-instances.sh`.
- Remember-me désactivé sur la superadministration.

- Administration regroupée Accueil / Pages du site / Messages / Comptes /
  Identité (remplacé depuis par le menu en quatre sections ci-dessus).
- Superadmin : édition in situ de la faculté choisie ; plus de création de
  site depuis l’écran Facultés ; sites `template` / `demo` exclus.
- Cookies de session par instance (`session.cookieName`).
- Documentation alignée sur cet état : audits datés et copies « créer un
  site » retirés des instances facultaires / superadmin.

## [Historique de reprise]

Reprise du starter CodeIgniter : multi-site `site_id`, Shield, messages,
utilisateurs, accueil dynamique, revues sécurité et accessibilité.
