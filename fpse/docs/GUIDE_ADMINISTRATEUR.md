# Guide administrateur

Ce guide décrit l’administration d’un site de faculté (`/admin`) et ce que
voit le visiteur. L’interface d’administration est en français ; les
textes anglais du site public se saisissent dans l’onglet « English »
lorsqu’il est proposé (un champ anglais vide affiche le texte français).

## Connexion

- **Personnel d’une faculté** : se connecter sur le `/admin` **de son
  site** avec l’adresse e-mail et le mot de passe fournis.
- **Superadministrateur** : se connecter sur l’adresse de la
  superadministration ; seul le groupe `superadmin` y est accepté.

Consulter le site public ne déconnecte pas ; la session se termine avec
**Déconnexion**. La case **Se souvenir de moi** (30 jours) n’existe que sur
les sites de faculté ; ne pas l’utiliser sur un ordinateur partagé.
**Voir le site** ouvre le site public dans un nouvel onglet.

## Qui peut faire quoi

| Rôle | Où | Droits |
|---|---|---|
| Éditeur | `/admin` de sa faculté | tableau de bord et pages du site ; ni messages, ni paramètres, ni comptes |
| Administrateur de faculté | `/admin` de sa faculté | les quatre sections du menu ; crée des administrateurs et des éditeurs de **sa** faculté |
| Superadministrateur | superadministration | plateforme et sections de la faculté **choisie** ; crée des administrateurs et éditeurs de faculté |

Le personnel d’une faculté ne peut pas administrer une autre faculté, même
en tapant une autre adresse. Une entrée de menu sans droit disparaît.

## Le menu

1. **Au quotidien** : tableau de bord (synthèse, messages récents, liste de
   mise en route tant que des textes « à remplacer » subsistent, actions
   rapides) et **Messages reçus** (la pastille compte les non lus).
2. **Pages du site** : un menu par page publique, dans l’ordre du site
   (Accueil, La Faculté, Départements, Formations, Admissions, FAQ,
   Recherche, Corps enseignant, Actualités & événements, Alumni, Contact,
   Mentions légales & confidentialité). Chaque menu contient :
   - **Sections de la page** : un petit formulaire par bloc visible, du haut
     vers le bas de la page ; enregistrer une section ne modifie que ses
     champs ;
   - **Listes** : les éléments que l’on ajoute ou retire (formations,
     départements, personnel, articles, laboratoires, témoignages…).
3. **Paramètres du site** : Identité & logo, Coordonnées, Réseaux sociaux,
   Pied de page, Référencement & partage.
4. **Administration** : Comptes & rôles.

**Ctrl+K** (ou le bouton **Rechercher**) cherche dans tout le menu : nom de
section, nom de page et libellés des champs, accents facultatifs
(« doyen » → La Faculté › Mot du doyen ; « téléphone » → Paramètres ›
Coordonnées). **Entrée** ouvre le premier résultat.

## Choisir une page pour un lien

Les boutons des pages (« Lien du bouton ») et les boutons du carrousel
réglés sur « Autre page ou lien externe » se remplissent avec un
**sélecteur de page** :

- cliquez dans le champ (ou sur la flèche) : la liste de toutes les pages du
  site s’ouvre, groupées en Pages principales, Départements, Formations,
  Corps enseignant et Actualités ;
- tapez le début d’un mot du titre (« form », « génie », « contact ») : la
  liste ne garde que les pages dont un mot commence ainsi, accents
  facultatifs ;
- choisissez avec la souris, ou avec les flèches et **Entrée** ;
- pour un site externe, collez son adresse complète (`https://…`) puis
  choisissez « Utiliser cette adresse » ;
- l’adresse retenue s’affiche sous le champ ; la croix vide le champ.

Le lien suit la langue du visiteur : une page choisie ici s’ouvre en
anglais sur la version anglaise du site.

## Les pages

### Bandeau

Chaque page sauf l’accueil a une section **Bandeau** : image de fond
(paysage, 1600 px de large au minimum, commune aux deux langues), titre et
sous-titre. Un titre vide affiche le nom standard de la page.

### Accueil

- **Carrousel d’images** : chaque image porte un texte alternatif, un
  badge, un titre, un texte et deux boutons ; le lien d’un bouton se choisit
  dans une liste (Formations, Contact, Actualités, Présentation, Présentation
  & mot du doyen, autre page ou lien externe, aucun bouton).
- **Présentation**, **Bloc recherche**, **Bloc formations**, **Bloc
  actualités** : petit libellé, titre, texte, bouton.
- **Ordre des blocs** : cocher et glisser-déposer les sections (carrousel,
  accès rapides, chiffres clés, présentation, formations, actualités,
  recherche, mot du doyen, équipe, mot d’accueil, contact). Les **accès
  rapides** sont quatre raccourcis fixes (Formations, Recherche, Actualités
  & événements, Contact) ; ils se masquent ou se déplacent comme les autres.
- **Référencement Google** : titre et description de la page d’accueil.
- Liste **Chiffres clés**.
- Pour l’aperçu sur l’accueil, cochez **Mis en avant sur l’accueil** (et
  l’ordre) sur les formations, laboratoires et membres du personnel ; pour
  les articles, **Mettre en avant sur l’accueil**. L’accueil montre au plus
  quatre personnes, huit actualités (mises en avant puis récentes) et les
  huit prochains événements.

### La Faculté

Mot du doyen (photo, nom, fonction, message en paragraphes, signature),
Mission & vision, Valeurs (trois), Historique, puis les listes **Frise
chronologique** et **Partenaires** (nom, type, pays, logo, site web). Les
partenaires publiés défilent en bandeau de logos ; il n’y a pas de page
Partenaires.

### Départements

Nom, sigle, **cycle** (licence, master ou les deux), présentation, chef de
département (liste du personnel), e-mail, téléphone et **maquette des
cours** : un PDF facultatif (10 Mo maximum, vérifié) avec son intitulé
(« Maquette des cours » ; la FSI conserve « Maquettes des cours FSI 2024-2025 » parce que le fichier déposé est celui de la faculté). La maquette se télécharge depuis la
section « Organisation des études » de la page du département ; cochez
« Supprimer » pour la retirer. Le département d’une formation ou d’un
membre du personnel se choisit dans sa fiche ; la liste ne propose que les
départements du niveau choisi.

### Formations

Chaque formation : niveau (**licence** ou **master** uniquement), département,
résumé, description, durée, crédits, langue, date limite, **conditions
d’accès** (points requis à l’Examen d’État ; les études sont gratuites pour
les lauréats), débouchés, lien « Candidater », coordinateur, brochure PDF
(10 Mo). Le **programme des cours** se saisit une ligne par cours :
`Semestre 1 | Intitulé du cours | 6` (crédits facultatifs).

**Options et parcours d’études.** Chaque option est une formation à part
(au département TIC : GI, GL, RT, SE). Dans la section « Parcours
d’études » :

- **Sigle de l’option** : affiché en pastille sur les cartes ;
- **Tronc commun** : donnez **le même nom** aux formations du même niveau
  qui suivent les mêmes cours au début, même dans des départements
  différents (« Tronc commun FSI ») ; la liste propose les noms déjà
  utilisés ; un tronc commun exige un département ;
- **Durée du tronc commun** : 1 à 3 ans ; vide = Bac I et Bac II en
  licence, première année en master ;
- **Tronc commun de filière** : second tronc plus étroit qui suit le
  premier (« Tronc commun GI-GL » en Bac II), avec sa durée (1 an par
  défaut).

La durée totale des troncs doit rester inférieure à celle de la formation.
Un tronc n’apparaît que s’il est partagé par au moins deux formations
publiées.

**Pas de doctorat.** Les études doctorales relèvent d’un site dédié :
renseignez son adresse dans Paramètres › Identité & logo (« Site des études
doctorales »). Tant que le champ est vide, rien ne s’affiche ; une fois
rempli, un encadré « Études doctorales » apparaît sous les formations et un
lien s’ajoute au pied de page.

Section de page **Appel à candidater** : titre, texte, bouton et lien
(sélecteur de page).

### Admissions et FAQ

Admissions : procédure (une étape par paragraphe), pièces du dossier,
texte « Gratuité et frais annexes », bouton de candidature. Le tableau des
dates limites se remplit seul depuis les formations ; les questions en bas
de page viennent de la FAQ (thème « Admissions »).

FAQ : chaque question porte un thème (Admissions, Frais et gratuité,
Études, Autres questions). La page `/faq` n’est listée dans le plan du site
que si une question est publiée.

### Recherche, Corps enseignant, Alumni

- Recherche : textes des blocs laboratoires, publications et projets, puis
  les trois listes. Projets et laboratoires sont indépendants.
- Corps enseignant : catégorie, département, photo, grade, spécialité,
  fonction, e-mail, biographie.
- Alumni : introduction, profils, témoignages, appel final ; un témoignage
  peut pointer vers un profil alumni.

### Actualités & événements

Statut (brouillon, programmé, publié, archivé), image, extrait, texte en
paragraphes séparés par une ligne vide (les balises `<p>` ou `<br>` collées
sont retirées), **Auteur** (prérempli, modifiable : « Décanat »), **PDF
joint** facultatif (10 Mo, « Retirer le fichier » pour le supprimer). Un
événement a des dates, un lieu, un lien d’inscription et propose « Ajouter
à mon agenda ».

### Contact et mentions légales

Contact : titres, texte d’aide du formulaire et carte intégrée (adresse
`https://` d’intégration Google Maps). L’adresse, le téléphone, l’e-mail et
les horaires se modifient dans Paramètres › Coordonnées.

Mentions légales : éditeur, hébergement, données personnelles, cookies. Un
champ vide affiche un texte générique neutre ; remplacez-le par les
informations réelles de l’université avant la mise en ligne.

## Messages reçus

Filtrer, ouvrir, marquer lu ou traité, archiver, supprimer (suppression
logique).

## Paramètres du site

Réservés à l’administrateur de faculté et au superadministrateur.
Enregistrer une page ne modifie que ses champs.

- **Identité & logo** : nom et sigle de la faculté, université, site web
  de l’université, site des études doctorales, logo.
- **Coordonnées** : avenue ou quartier, commune, province, pays,
  téléphone, e-mail, horaires.
- **Réseaux sociaux** et **Pied de page** (texte, copyright).
- **Référencement & partage** : titre et description par défaut, couleur
  du thème, image de partage, et mesure d’audience facultative sans cookie
  (Plausible ou Matomo : outil, adresse `https://` de l’instance,
  identifiant ; le script n’est chargé que si les trois champs sont remplis).

## Comptes & rôles

- **Côté faculté** : comptes de cette faculté ; deux cartes de rôle,
  Administrateur (gère aussi messages, paramètres et comptes) ou Éditeur
  (contenus uniquement). On ne peut pas se retirer soi-même l’accès.
- **Côté superadministration** : choisir le rôle (Administrateur ou
  Éditeur de faculté), puis cocher une ou plusieurs facultés. Un compte sans
  rôle ou sans faculté est refusé.
- **Superadministrateurs** : ils ne se créent **pas** depuis l’écran, mais
  en ligne de commande sur le serveur de la superadministration ; leur ligne
  porte le badge « Superadministrateur (ligne de commande) », sans bouton.
- **Supprimer un compte** l’efface définitivement ; le même nom
  d’utilisateur et la même adresse e-mail peuvent ensuite être réutilisés.

## Superadministration

1. Le tableau de bord liste les facultés réelles.
2. **Gérer le contenu** ou le sélecteur en haut de l’écran choisit la
   faculté active : le menu affiche alors ses pages.
3. L’édition se fait ici, sans ouvrir le site de la faculté.
4. **Facultés** permet de modifier le nom, le statut et les couleurs d’une
   faculté (nuance du vert ou du rouge du logo de l’université ; toutes les
   nuances du site en découlent, assombries si nécessaire pour rester
   lisibles). Les domaines ne se modifient pas depuis l’écran et aucune
   faculté ne se crée depuis l’écran.

## Ce que voit le visiteur

- **Menu** : deux menus déroulants, La Faculté (Faculté, Départements) et
  Formations (Formations, Admissions, FAQ), puis Recherche, Corps
  enseignant, Actualités, Alumni, Contact ; une loupe ouvre la recherche
  sur tout le site.
- **Langue** : français par défaut ; la version anglaise a sa propre
  adresse (`?lang=en`). Sans traduction, le texte français s’affiche.
- **Accueil** : carrousel, accès rapides, chiffres clés, présentation,
  formations par niveau, carrousels « Actualités » et « Événements »,
  recherche, mot du doyen, équipe, mot d’accueil, bloc contact (ordre réglé
  par l’administration).
- **Départements** : regroupés par cycle ; chaque page présente les
  chiffres clés, le chef, l’« Organisation des études » (troncs puis
  options, avec liens vers les départements qui partagent un tronc), la
  maquette PDF, les formations et le personnel.
- **Formations** : licences et masters, filtre par département ; la fiche
  affiche niveau, durée, crédits, langue, date limite, conditions d’accès,
  étapes du parcours, programme par semestre, débouchés, coordinateur,
  brochure et boutons « Candidater » et « Comment candidater ».
- **Admissions** : procédure en étapes, pièces, gratuité, dates limites,
  questions fréquentes. **FAQ** : questions par thème, réponses dépliables.
- **Corps enseignant** : filtres par catégorie, département, grade et nom ;
  pages de 24 personnes.
- **Actualités** : filtres par type et « À venir / Passés » ; auteur, PDF
  joint, fichier agenda pour les événements.
- **Contact** : nom, e-mail, sujet et message obligatoires, téléphone
  facultatif ; le message arrive dans « Messages reçus ».
- **Pied de page** : liens rapides, coordonnées, réseaux sociaux, site de
  l’université et autres facultés, mentions légales, lien Administration.
- **Cookies** : seulement ceux nécessaires (session, sécurité des
  formulaires, langue) ; la mesure d’audience éventuelle est sans cookie et
  signalée dans les mentions légales.
