# Journal des changements

Ce fichier suit les changements visibles du projet. Les dates ne sont
ajoutées que lorsqu’elles sont vérifiables.

## [Non publié]

- Administration : **sélecteur de page** pour les liens (boutons des
  sections de page, boutons « Autre page ou lien externe » du carrousel).
  On tape le début d’un mot du titre (« form ») et la liste propose les pages
  correspondantes : pages principales, départements, formations, personnel
  et actualités publiés de la faculté ; une adresse `https://` reste
  possible. La valeur enregistrée est inchangée (chemin relatif ou adresse
  complète).
- Documentation réécrite et regroupée en français : `README.md`,
  `AGENTS.md`, `docs/ARCHITECTURE.md`, `GUIDE_ADMINISTRATEUR.md`,
  `DEPLOIEMENT.md`, `OPERATIONS_LOCALES.md`, `SECURITE_QUALITE.md`,
  recopiés dans chaque instance par `sync-instances.sh`. Les guides de
  création (`CREER_UN_SITE.md`, `AGENT_NOUVEAU_SITE.md`) restent dans le
  modèle ; le cahier des charges d’origine est archivé.
- Parcours d’études : arbre « Organisation des études » (tronc commun,
  tronc commun de filière, options) sur les pages département et formation ;
  un tronc commun peut réunir des formations de départements différents
  (tronc commun FSI) ; durées réglables (`common_core_years`,
  `branch_core_years`). **Maquette des cours** PDF facultative par
  département.
- Plus de doctorat sur les sites de faculté : niveaux licence et master
  seulement ; encadré et lien de pied de page vers le site des études
  doctorales quand son adresse est renseignée (Paramètres › Identité).
- Comptes : le superadministrateur crée uniquement des **administrateurs**
  et des **éditeurs de faculté**, rattachés à une ou plusieurs facultés
  (choix du rôle en deux cartes, liste de facultés à cocher ; le rôle sur
  chaque faculté suit le rôle choisi). Les superadministrateurs se créent
  seulement en ligne de commande (`php spark admin:create-superadmin`, qui
  accepte maintenant ses options en paramètres et refuse un nom
  d’utilisateur déjà pris) ; leurs lignes sont en lecture seule dans la
  liste. Colonne « Faculté(s) » avec badges de rôle.
- Correction : supprimer un compte l’efface définitivement ; le même nom
  d’utilisateur et la même adresse e-mail peuvent être réutilisés. Une
  migration purge les comptes supprimés auparavant qui bloquaient encore
  leur nom d’utilisateur.
- Formations : **options de département** (sigle GI, GL, RT…) et **tronc
  commun** partagé entre formations du même niveau (par défaut Bac 1 et
  Bac 2 en licence, première année en master). Encadré « Tronc
  commun » avec liens sur la page de l’option, mention « Tronc commun
  avec … » sur les cartes, regroupement par tronc commun sur la page du
  département, sigles des options sur la page Départements ; champ avec
  suggestions dans l’administration, un tronc commun exige un département.
- Démo FSI : départements réels (Génie civil et environnemental,
  Architecture et urbanisme, Électromécanique, TIC) ; options TIC RT, GI,
  GL et SE en licence (GI et GL en tronc commun Informatique), GL et RT en
  master.
- Futurs étudiants : pages **Admissions** (procédure en étapes numérotées,
  pièces du dossier, encadré « Gratuité et frais annexes », tableau des dates
  limites en cartes sur téléphone, FAQ admissions, bandeau « Candidater »),
  **Départements** regroupés par cycle (licence et master ; fiche
  `/departements/{slug}` avec chiffres clés, chef, formations et personnel)
  et **FAQ** par thème (pastilles collantes, bandeau « Encore une
  question ? »). Fiche formation enrichie : crédits, langue, date limite,
  **Conditions d’accès** (points requis à l’Examen d’État ; les études sont
  gratuites), programme des cours par semestre avec total des crédits,
  coordinateur, brochure PDF, boutons « Candidater » et « Comment
  candidater » ; filtre par département limité aux départements du niveau
  choisi sur `/formations`.
- Départements : champ **Cycle** (licence, master ou les deux) ; dans le
  formulaire d’une formation ou d’un membre du personnel, la liste des
  départements suit le niveau choisi et une formation de licence est refusée
  dans un département réservé au master.
- Partenaires : plus de page dédiée ; tous les partenaires publiés défilent
  en bandeau de logos sur La Faculté (défilement seulement s’ils dépassent
  la largeur, pause au survol, arrêt si « réduire les animations » est
  activé).
- Actualités et événements : champ **Auteur** (prérempli avec le nom
  d’utilisateur) affiché sur les cartes et la page détail ; **fichier PDF
  joint** (10 Mo, retrait possible) proposé en téléchargement ; les textes
  collés avec des balises `<p>` ou `<br>` s’affichent en paragraphes
  propres. Accueil : deux carrousels séparés « Actualités » et
  « Événements » (événements à venir) avec flèches aux bords.
- Grand public : « Ajouter à mon agenda » (`.ics`) sur chaque événement ;
  filtre « À venir / Passés » sur les événements ; colonne Université (site
  de l’université, autres facultés) et lien **Administration** dans le pied
  de page (remplace le lien vers le plan du site ; `sitemap.xml` reste
  publié). Menu public compact sur une ligne en français comme en anglais,
  recherche repliée en icône sur ordinateur.
- Administration : les icônes ↗ à côté de chaque page du menu « Pages du
  site » sont retirées ; utiliser « Voir le site ». Migration
  `AdjustInformationModules` (suppression de `programmes.tuition_fees`, ajout
  de `entry_requirement`, `departments.cycle`, `posts.author_name`,
  `attachment_path`, `attachment_name`).
- Pages mentions légales (sommaire collant, titres numérotés), recherche et
  personnel (panneau de filtres, cartes, états vides) harmonisés.
- Confiance et référencement : page **Mentions légales & confidentialité**
  (textes génériques par défaut), note de confidentialité sous le formulaire
  de contact, `sitemap.xml` par site avec alternates, `robots.txt`
  dynamique, adresses anglaises `?lang=en` avec canonical et hreflang,
  JSON-LD (organisation, formation, personne, événement, FAQ), recherche
  élargie aux départements, laboratoires, publications, projets, FAQ et
  partenaires, filtres (département, grade, nom) et pagination par 24 du
  personnel, mesure d’audience facultative sans cookie (Plausible ou
  Matomo, CSP ajustée seulement si activée).
- Menu public en menus déroulants : La Faculté (Faculté, Départements),
  Formations (Formations, Admissions, FAQ) ; Actualités reste un lien
  simple.
- Administration : listes Départements, Partenaires et Questions
  fréquentes ; nouveaux types de champ date et fichier PDF (vérification
  extension, type MIME et signature) ; réglages adresse de l’université et
  mesure d’audience. Migration `AddFacultyInformationModules` ; le seed démo
  et le provisioning créent les nouvelles pages (contenu neutre pour le
  provisioning).
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
