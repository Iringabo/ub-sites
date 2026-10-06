# Consignes pour un agent : préparer le site d’une nouvelle faculté

Document réservé au modèle (jamais recopié dans une instance). Il dit à un
agent comment rechercher, rédiger et ranger les données d’une nouvelle
faculté de l’Université du Burundi (UB) pour que `php spark site:seed-demo`
produise un site complet, en français et en anglais, sur le modèle des cinq
facultés existantes (FSEG, FSI, MED, FABI, FLSH). La procédure d’ouverture
d’un dossier est dans [CREER_UN_SITE.md](CREER_UN_SITE.md).

Dans tout ce document, `{slug}` est l’identifiant en minuscules du dossier
et du site (ex. `fdroit`) et `{key}` la clé de thème courte utilisée dans les
services de démonstration (ex. `law` ; existantes : `eco`, `sci`, `med`,
`agro`, `hum`).

---

## 1. Objectif et livrables

Le travail est terminé quand ces éléments existent, et rien d’autre :

| Livrable | Emplacement |
|---|---|
| Thème et contenus | `template/app/Services/FacultyDemoDataService.php` : entrée `{slug}` dans `themeForSlug()`, nouvelle méthode `theme…()`, entrées `{key}` dans `programmeSet()` (et son tableau `$admission`), `staffSet()`, `postSet()`, `labSet()`, `publicationSet()`, `projectSet()`, `timelineSet()`, `alumniSet()`, `testimonialSet()`, `normalizeThemeMeta()` (`$shortLabels`), `campusForKey()`, `englishPackForKey()` |
| Départements et partenaires | `template/app/Services/FacultyDemoInformationSeeder.php` : `departmentSet()` et `partnerSet()` (`$specific`) |
| Couleur | `FacultyDemoDataService::BRAND_COLORS['{slug}']` |
| Photos | `template/demo-media/faculties/{slug}/` : `hero-1.jpg`, `hero-2.jpg`, `hero-3.jpg`, `banner.jpg`, `SOURCES.md` |
| Maquette réelle (si fournie) | `template/app/Database/DemoAssets/maquettes/{slug}.pdf`, branchée dans `seedMaquettes()` comme celle de la FSI |
| Carte | entrée `{slug}` dans `scripts/update-faculty-maps.php` (racine) |
| Scripts | `{slug}` ajouté à la boucle de `template/scripts/sync-instances.sh`, à `dev-serve.sh` (port suivant libre), à `scripts/ensure-faculty-admins.php` et à `app.uploadMirrors` du `.env` de `superadmin/` |

Le code générique (contrôleurs, vues, modèles, migrations,
`TemplateStarterSeeder`, `FacultySiteProvisioningService`, fichiers de
langue) **ne change pas**. Si une donnée ne rentre dans aucun champ
existant, on la laisse de côté.

## 2. Interdits

- **Doctorat** : aucune formation, aucun texte, aucun département
  « doctoral ». Les études doctorales sont sur un site séparé ; seul le
  réglage `links.doctoral_studies_url` y renvoie.
- **Frais de scolarité** : aucun montant. Les études à l’UB sont gratuites
  pour les lauréats de l’Examen d’État qui atteignent les points fixés par
  le ministre de l’Éducation. On renseigne les conditions d’accès.
- **Personnes réelles** : aucun nom ni photo de vrai membre du personnel, de
  vrai diplômé ni de vrai doyen. Noms burundais vraisemblables mais
  fictifs.
- **Données inventées présentées comme réelles** : pas de département,
  d’option, de laboratoire officiel ou de partenaire nommé qui n’existe pas.
  Un laboratoire ou un projet de démonstration porte un nom générique
  plausible, jamais celui d’une vraie structure qu’on n’a pas vérifiée.
- **Images** : pas d’adresse d’image distante, pas de photo d’une autre
  faculté, pas de logo généré, pas de bandeau de couleur unie, pas de photo
  hors sujet.
- **Mise en forme** : pas de balise `<p>`, `<br>` ni de HTML dans les
  textes ; paragraphes séparés par `\n\n`.
- **Répétition** : aucune phrase reprise d’une page à l’autre ni d’une
  faculté à l’autre (y compris les cinq existantes). La Présentation de
  l’accueil, le Mot d’accueil, le Mot du doyen et la Mission disent des
  choses différentes.
- **Modèle neutre** : aucune donnée réelle dans les migrations, les vues,
  les mentions légales, `TemplateStarterSeeder` ou le provisioning.
- **Modules retirés** : pas de page Agenda, pas de page Partenaires, pas de
  « points forts » (`highlights`) à rédiger.

## 3. Recherche des données

### Sources, par ordre de priorité

1. **Documents fournis par la faculté** (maquettes, plaquettes, arrêtés,
   listes de départements) : ils priment sur tout le reste. Exemple : la
   maquette FSI 2024-2025 a corrigé les départements et troncs communs
   affichés sur le site web.
2. Le site de la faculté `https://{slug}.ub.edu.bi` (s’il existe).
3. Les programmes et maquettes 2024-2025 publiés sur `https://ub.edu.bi`.
4. Les actualités de l’UB (`ub.edu.bi`) pour l’historique, les partenariats
   et les événements.

### Vérifié ou complété

- Rassembler d’abord ce qui est vérifié, avec sa source.
- Ce qui manque peut être complété de façon plausible ; le noter
  « (à confirmer) » **dans la fiche de collecte seulement**, jamais dans le
  texte publié.
- Avant le seed, retirer tout élément « (à confirmer) » qui touche
  l’identité réelle de la faculté (départements, options, adresse,
  téléphone). Seul le contenu d’illustration (articles, personnel fictif,
  publications d’exemple) peut rester complété.

### Faits UB à respecter

- Licence : 4 ans, 240 crédits (Bac I à Bac IV), 60 crédits par an,
  30 par semestre. Médecine : 6 ans, 360 crédits. Master : 2 ans,
  120 crédits.
- Niveaux autorisés : `licence` et `master` uniquement.
- Organisation : **département → options → troncs communs**.
  - Un département a un `cycle` : `licence`, `master` ou `both`.
  - Chaque option est une formation à part avec un `option_code` (GI, GL…).
  - `common_core` : tronc commun partagé par plusieurs formations du même
    niveau, éventuellement de départements différents (tronc commun FSI du
    Bac I pour EM, GC et AU) ; `common_core_years` (vide = 2 ans en licence,
    1 an en master).
  - `branch_core` : second tronc plus étroit qui suit le premier (TIC :
    Bac I commun, puis Bac II commun GI-GL ou SE-RT) ; `branch_core_years`
    (1 par défaut).
  - Écrire les noms de tronc **exactement pareil** dans toutes les
    formations qui le partagent (« Tronc commun FSI »), avec leur traduction
    anglaise.
- Conditions d’accès : licence = Examen d’État avec les points fixés par le
  ministre (préciser la section si la faculté l’exige) ; master = licence du
  domaine et sélection sur dossier.

## 4. Fiche de collecte

Remplir cette fiche (dans le plan de travail, pas dans le dépôt) avant
d’écrire du code.

**Identité**
- Nom officiel complet, sigle, slug, année de création, campus.
- Couleur choisie (section 8).

**Coordonnées**
- Avenue ou quartier, commune, province, pays (champs séparés).
- Téléphone, e-mail (`{local}@ub.edu.bi`), horaires.
- Coordonnées GPS du bâtiment et libellé de carte.
- Réseaux sociaux officiels (adresses vérifiées seulement, sinon vide).

**Organisation des études**
- Départements : nom, sigle, cycle, présentation, chef (fictif, pris dans
  le personnel).
- Options de licence par département, avec sigle.
- Troncs communs : nom, formations concernées, durée ; troncs de filière.
- Masters : intitulé, département, sigle.
- Maquettes PDF disponibles (une par département ou une commune).

**Contenus d’illustration**
- Historique (6 dates), laboratoires, publications, projets, alumni,
  témoignages, articles, partenaires (section 6).

## 5. Où va chaque texte

Les sections de page suivent `PageTextCatalog` ; la colonne « Clé » donne
l’entrée du thème (`theme…()` de `FacultyDemoDataService`) ou la source.
Ton : institutionnel, clair, phrases courtes, vouvoiement implicite, sans
superlatifs vides (« excellence » une fois au plus par page).

### Accueil

| Section | Clé | Contenu attendu |
|---|---|---|
| Carrousel | `hero_captions` (badge, title, text) ; le texte de la 1re diapositive vient de `tagline` | 3 diapositives au moins : identité, formations, recherche ou campus. Titre 4 à 8 mots, texte 1 phrase (≤ 140 caractères) |
| Présentation | `about_title`, `about` | qui est la faculté et ce qu’elle forme : 2 ou 3 phrases (300 à 450 caractères) |
| Bloc recherche | `research_title`, `research_body` | axes réels des laboratoires, 2 phrases |
| Bloc formations | `programmes_title`, `programmes_intro` | domaines couverts et logique licence-master, 1 ou 2 phrases |
| Bloc actualités | `posts_title`, `posts_intro` | types d’annonces publiées, 1 phrase |
| Mot d’accueil | `welcome` | orientation du visiteur (où aller selon son profil), 1 ou 2 phrases, différent de la Présentation |
| Chiffres clés | `stats` (sections `home_main`, `home_research`, `alumni`) | chiffres vérifiés ; sans source fiable, ne garder que ce qui se compte depuis les données (formations, laboratoires) |
| Référencement | `seo_description` | 140 à 160 caractères, nom complet + domaines |

Les boutons de l’accueil pointent vers des pages du site (`/formations`,
`/faculte`, `/recherche`, `/actualites`) ; ne pas les changer.

### La Faculté

| Section | Clé | Contenu attendu |
|---|---|---|
| Bandeau | `faculty_banner` | nom complet ou devise, une ligne |
| Mot du doyen | `dean_name`, `dean_role`, `dean_specialty`, `dean_title`, `dean_label`, `dean_message`, `dean_signature` | doyen fictif (= 1re personne de `staffSet`) ; 3 paragraphes : accueil, projet de la faculté, invitation |
| Mission & vision | `mission`, `vision` | un paragraphe chacun (50 à 80 mots), concret : publics formés, besoins du pays |
| Valeurs | `values` (3 × title, text) | un mot + une phrase (≤ 25 mots) |
| Historique | `history` (label, title, text) | une phrase d’accroche |
| Liste Frise chronologique | `timelineSet` (year, title, description) | 6 dates vérifiées (création, réformes, nouveaux départements, bâtiments) |
| Liste Partenaires | `partnerSet` | 3 partenaires communs déjà fournis + 2 propres à la faculté : [nom, type `academic`/`institution`/`industry`/`ngo`, pays, description, nom anglais, description anglaise] |

### Départements

Les textes d’introduction de la page sont communs à toutes les facultés
(`FacultyDemoInformationSeeder::seedPages()`) ; ne rien rédiger. Pour chaque
département (`departmentSet`) : sigle, slug, cycle, nom, présentation
(1 ou 2 phrases : options, troncs, masters), nom et présentation anglais,
slugs des formations, slugs du personnel (le premier devient chef).

Maquette : par défaut, un PDF est généré à partir du programme des cours. Si
la faculté fournit une vraie maquette, la placer dans
`app/Database/DemoAssets/maquettes/{slug}.pdf` (10 Mo maximum) et
l’utiliser dans `seedMaquettes()` comme la FSI.

### Formations

| Section ou liste | Clé | Contenu attendu |
|---|---|---|
| Bandeau | `formations_banner` | une ligne sur les domaines |
| Présentation de l’offre | `offer_label`, `offer_title`, `offer_text` | organisation des parcours (troncs puis options), 1 ou 2 phrases |
| Appel à candidater | `cta_title`, `cta_text`, `cta_label` | invitation à candidater ou à contacter la scolarité |
| Liste Formations | `programmeSet` : `$p(level, slug, title, summary, description, careers, extra)` | titre officiel ; résumé ≤ 160 caractères ; description 2 à 4 phrases (département, tronc, spécialisation, stage ou mémoire) ; 3 ou 4 débouchés ; `extra` = `$core(code, tronc FR, tronc EN, années, tronc de filière FR, EN, années)` ou `['option_code' => …]` |
| Conditions d’accès | `$admission['{key}']` (licence, master) | section du Diplôme d’État exigée, sélection en master |

La durée (4 ans, 2 ans) et les crédits sont calculés ; le programme des
cours est généré par semestre (troncs identiques entre formations qui les
partagent). Les 6 premières formations sont mises en avant sur l’accueil :
les ordonner en conséquence.

### Admissions, FAQ, Mentions légales

Textes communs à l’UB (procédure, pièces, gratuité, questions fréquentes,
mentions génériques) déjà fournis par `FacultyDemoInformationSeeder`. Ne
rien rédiger, sauf exception propre à la faculté (concours d’entrée en
spécialisation en Médecine, par exemple), à traiter dans les conditions
d’accès des formations.

### Recherche

| Section ou liste | Clé | Contenu attendu |
|---|---|---|
| Bandeau | `research_banner` | sigles des laboratoires ou axes |
| Section publications | `publications_title`, `publications_intro` | 1 phrase |
| Section projets | `projects_title`, `projects_intro` | 1 phrase, types de bailleurs |
| Liste Laboratoires | `labSet` : abbr, name, slug, icon (`bi-…`), description, themes (4), researchers | description 2 ou 3 phrases ; nom réel seulement s’il est vérifié |
| Liste Publications | `publicationSet` : year, title, authors, journal | auteurs = personnel fictif (« Nom P. ») ; revues génériques plausibles |
| Liste Projets | `projectSet` : title, description, funder, start, end, code, icon | bailleurs de type réel (UE, coopération bilatérale) sans prétendre à un vrai contrat |

### Corps enseignant

`staff_banner` (une ligne) et `staffSet` : category (`enseignant` ou
`administratif`), name, slug, grade (Professeur Ordinaire, Professeur,
Professeur Associé, Maître de Conférences, Assistant…), specialty, role,
email, bio (2 phrases). La première personne est le doyen ; prévoir un vice-
doyen, les chefs de département et deux personnels administratifs
(secrétariat, scolarité).

### Actualités & événements

`posts_banner` (une ligne) et `postSet` : type (`news` ou `event`), title,
slug, excerpt (1 ou 2 phrases), body (3 paragraphes séparés par `\n\n`),
location pour un événement. Mélanger rentrée, conférence, soutenances,
partenariat, résultats, vie étudiante. Les dates, auteurs et images sont
attribués au seed ; les trois premiers articles sont mis en avant.

### Alumni

| Section ou liste | Clé | Contenu attendu |
|---|---|---|
| Bandeau | `alumni_banner` | une ligne |
| Introduction | `alumni_intro` (2 paragraphes) | secteurs où travaillent les diplômés |
| Section profils | `alumni_profiles_text` | 1 phrase |
| Appel final | `alumni_cta` | inviter à mettre à jour son profil |
| Liste Profils | `alumniSet` : name, slug (`alumni-…`), role, organization, year, bio | fictifs ; organisations de type réel (banque, ministère, ONG) sans nommer une vraie personne |
| Liste Témoignages | `testimonialSet` : name, promotion, quote | les mêmes noms que des profils ; citation 1 phrase |

### Contact et paramètres

| Élément | Clé | Contenu attendu |
|---|---|---|
| Bandeau | `contact_banner` | accroche + campus |
| Bloc coordonnées, formulaire | `contact_label`, `contact_title`, `form_title`, `form_help` | libellés courts |
| Adresse, téléphone, e-mail, horaires | `campusForKey()` : label, address_line, commune, province, country, phone, email_local, hours, lat, lon | données vérifiées |
| Carte | `scripts/update-faculty-maps.php` | lat, lon, libellé, adresse, zoom 17 |
| Réglages | `seedSettings()` | nom, sigle, université, adresse, pied de page, référencement : remplis automatiquement à partir des éléments ci-dessus |

Après le seed, renseigner les réseaux sociaux réels et l’adresse du site
des études doctorales depuis l’administration (Paramètres), pas dans le
code.

## 6. Volumes minimaux

| Élément | Minimum |
|---|---|
| Formations (licence + master) | 8 |
| Départements | 3 à 4 |
| Personnel | 12 |
| Articles et événements | 9 (dont 3 événements) |
| Laboratoires | 3 |
| Publications | 6 |
| Projets | 4 |
| Dates de la frise | 6 |
| Profils alumni | 4 |
| Témoignages | 3 |
| Diapositives du carrousel | 3 |
| Partenaires | 5 (3 communs + 2 propres) |
| Questions FAQ | environ 8 (fournies) |

## 7. Images

- **À fournir** : `hero-1.jpg`, `hero-2.jpg`, `hero-3.jpg` et `banner.jpg`
  dans `template/demo-media/faculties/{slug}/`. Paysage, JPEG, 1600 px de
  large au moins, 2 Mo au plus (compresser si besoin). Ces quatre fichiers
  servent aussi d’images de couverture aux articles.
- **Sujet** : la discipline de la faculté et son contexte (salle de cours,
  laboratoire, terrain, campus africain). Chaque photo est propre à cette
  faculté : jamais une photo déjà utilisée par une autre.
- **Source, par ordre de priorité** : photos fournies par la faculté ou par
  l’UB ; sinon photos sous licence libre (Unsplash, Pexels) en rapport avec
  le sujet. Noter dans `SOURCES.md` du dossier, pour chaque fichier :
  auteur, adresse de la page d’origine, licence.
- **Téléchargement** : les fichiers sont enregistrés hors ligne dans le
  pack ; le seed les recopie dans `public/uploads/sites/{slug}/`. Aucune
  adresse distante dans le code.
- **Portraits** : personnel, alumni et témoignages reçoivent des avatars à
  initiales générés (640 × 640) ; ne pas chercher de photos de personnes.
  La photo du doyen est le portrait de la première personne du personnel.
- **Logo** : le blason partagé de l’UB (`assets/images/logo-placeholder.png`).
- **Maquettes PDF** : 10 Mo au plus.

## 8. Couleur

Choisir une nuance du **vert** ou du **rouge** du logo de l’UB, distincte des
couleurs existantes (FSEG `#0D9B49`, FSI `#0B6F38`, FABI `#4E8F2F`, MED
`#C8102E`, FLSH `#8B1E2D`), et l’ajouter à
`FacultyDemoDataService::BRAND_COLORS`. Toutes les nuances du site en
découlent ; le contraste est corrigé automatiquement.

## 9. Anglais

- Le site public est servi en anglais à `?lang=en` ; l’administration reste
  en français.
- Fournir l’anglais de chaque texte visible :
  - accueil, La Faculté, Formations et Contact :
    `englishPackForKey('{key}')` (tagline, about_title, about, hero_title,
    dean_title, dean_role, dean_specialty, dean_paragraphs, mission_label,
    mission_title, mission, vision, history_title, history_text,
    research_title, research_body, offer_title, offer_text, cta_title,
    cta_text, contact_title, form_title, form_help, faculty_banner,
    contact_banner, seo_title, seo_description, footer, hours) ;
  - troncs communs : arguments anglais de `$core()` ;
  - départements et partenaires : colonnes anglaises de `departmentSet()` et
    `partnerSet()`.
- Les titres et résumés des formations et des articles sont pour l’instant
  recopiés tels quels en anglais par le seed. Pour un vrai texte anglais,
  ajouter les champs anglais dans les données de démonstration de la
  faculté et les insérer dans `seedEnglishTranslations()`, sans toucher au
  code générique.
- Anglais naturel (britannique), pas de traduction mot à mot ; mêmes faits
  que le français.

## 10. Procédure technique

1. Ouvrir le dossier de la faculté selon
   [CREER_UN_SITE.md](CREER_UN_SITE.md) : copie du modèle, `.env`
   (`app.siteSlug={slug}`, `session.cookieName=ci_session_{slug}`,
   `app.baseURL`), `php spark key:generate`, `php spark migrate --all`,
   `php spark site:create --identifier … --slug {slug} --name "…"`,
   `php spark admin:create-faculty-admin`.
2. Écrire les données (sections 1 à 9) dans `template/`.
3. `cd template && ./scripts/sync-instances.sh`.
4. `cd superadmin && php spark site:seed-demo --slug={slug} --force`.
5. À la racine : `php scripts/update-faculty-maps.php`.
6. `cd fseg && php spark testdb:migrate`, puis les tests concernés, un
   fichier à la fois :
   `php vendor/bin/phpunit --configuration phpunit.dist.xml --no-logging tests/feature/DemoPageContentShapeTest.php`
   (puis `FacultyInformationPagesTest.php`, `MultiSiteIsolationTest.php`).
7. `./scripts/dev-serve.sh start` et contrôle (section 11).

## 11. Contrôle final

- [ ] Chaque page publique s’ouvre sans erreur en français et avec
      `?lang=en` : accueil, `/faculte`, `/departements` et une fiche,
      `/formations` et une fiche, `/admissions`, `/faq`, `/recherche`,
      `/corps-enseignant`, `/actualites` et un article, `/alumni`,
      `/contact`, `/mentions-legales`.
- [ ] Les volumes de la section 6 sont atteints.
- [ ] Aucun « doctorat », « doctoral » ni montant de frais dans les pages
      (recherche dans le site et dans les données).
- [ ] L’arbre « Organisation des études » d’un département montre les bons
      troncs et options ; la maquette se télécharge.
- [ ] Les photos sont celles de la faculté (aucune reprise d’une autre
      faculté) ; `SOURCES.md` est rempli.
- [ ] Aucune phrase reprise d’une autre faculté ou d’une autre page.
- [ ] La carte de la page Contact montre le bon bâtiment.
- [ ] Captures d’écran de l’accueil, d’une fiche formation et d’une page
      département, sur ordinateur et sur téléphone, jointes au compte
      rendu.
- [ ] Comptes temporaires supprimés, serveurs arrêtés
      (`./scripts/dev-serve.sh stop`).
