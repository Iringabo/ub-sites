# Revue Accessibilité Et Responsive

Dernière actualisation documentaire : 11 août 2026.

Cette revue décrit l'état documentaire actuel et les limites à vérifier manuellement. Elle ne prétend pas remplacer un audit WCAG complet.

## Éléments Présents Dans Le Code

- Layouts public, admin et auth séparés.
- Navigation Bootstrap responsive.
- Lien d'évitement vers le contenu principal.
- Styles de focus visibles.
- Prise en compte de `prefers-reduced-motion`.
- Libellés visibles en français pour l'authentification et l'administration.
- Sélecteur de langue public FR/EN.
- Tests unitaires dédiés dans `tests/unit/AccessibilityResponsiveTest.php`.

## Public

Pages à valider :

- accueil;
- faculté;
- formations et détail;
- recherche;
- personnel et détail;
- actualités/événements et détail;
- alumni;
- contact.

Contrôles attendus :

- menu mobile utilisable;
- pas de débordement horizontal;
- textes lisibles;
- images non déformées;
- focus clavier visible;
- formulaires lisibles avec erreurs;
- langue HTML cohérente avec la locale.

## Administration

Écrans à valider :

- dashboard;
- listes avec filtres;
- formulaires génériques;
- posts;
- messages;
- utilisateurs;
- sélection de site;
- paramètres et sites facultaires.

Contrôles attendus :

- sidebar exploitable sur mobile;
- tableaux défilables sans casser la page;
- actions visibles et compréhensibles;
- messages de succès/erreur lisibles;
- champs longs sans chevauchement.

## Largeurs À Tester

- 320 px
- 375 px
- 768 px
- 1024 px
- 1440 px

## Limites De La Revue Actuelle

- Une validation navigateur complète avec captures n'a pas été rejouée dans cette remise à niveau documentaire.
- Les contrastes réels doivent être vérifiés avec les couleurs et images finales de chaque faculté.
- Tous les actifs frontaux sont auto-hébergés (`public/assets/vendor/`) : le rendu ne dépend d'aucun service externe.
- Les contenus définitifs longs peuvent créer des cas non visibles avec les données de démonstration.

## Critères Avant Livraison

- Aucun débordement horizontal majeur.
- Navigation clavier possible.
- Focus visible sur liens, boutons et champs.
- Formulaires compréhensibles avec erreurs.
- Administration utilisable sur tablette et desktop.
- Version mobile publique validée avec les contenus finaux.
