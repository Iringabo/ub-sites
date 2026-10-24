# Sécurité, accessibilité et critères de livraison

État réel du code et contrôles à mener avant une mise en ligne. Ce document
ne remplace pas un audit externe.

## Authentification et rôles

- CodeIgniter Shield ; l’administration exige `session` et
  `permission:admin.access`.
- Groupes : `superadmin`, `admin`, `editor`. Permissions :
  `admin.access`, `home.manage`, `news.manage`, `events.manage`,
  `programmes.manage`, `staff.manage`, `research.manage`, `alumni.manage`,
  `pages.manage`, `messages.manage`, `users.manage`, `settings.manage`,
  `sites.manage`.
- `SiteScopeFilter` limite le personnel d’une faculté à son site ; les
  identifiants d’un autre site renvoient 404.
- L’instance de superadministration n’accepte que le groupe `superadmin`.
  Les superadministrateurs se créent **uniquement** en ligne de commande
  (`admin:create-superadmin`, dans `superadmin/`) ; l’écran ne peut ni en
  créer, ni en modifier, ni en supprimer.
- La gestion des comptes empêche de se retirer son propre accès, de
  désactiver le dernier superadministrateur et d’attribuer `sites.manage`.
- La suppression d’un compte est définitive (identités, groupes,
  permissions, sessions « se souvenir de moi », rattachements aux sites) ;
  le nom d’utilisateur et l’e-mail redeviennent disponibles.
- « Se souvenir de moi » est désactivé sur la superadministration.

## Sessions, CSRF et en-têtes

- Filtres globaux `csrf` (avant) et `secureheaders` (après).
- Toute modification passe par POST ; aucune suppression par GET.
- Un nom de cookie de session distinct par instance
  (`session.cookieName`), pour que deux sites ouverts dans le même
  navigateur ne partagent pas la session.

## Content Security Policy

Activée dans `app/Config/App.php` et `.env.example` ;
`app:production-check` contrôle la valeur réellement chargée.

- `object-src 'none'`, `frame-ancestors 'self'`,
  `script-src-attr 'none'` : aucun style ni script en ligne dans les vues ;
  le JavaScript vit dans `public/assets/js/`, les styles dans
  `public/assets/css/style.css`.
- Actifs auto-hébergés (`public/assets/vendor/` : Bootstrap 5.3.3,
  Bootstrap Icons 1.11.3, police Inter) ; aucun CDN.
- Exceptions : `frame-src https://www.google.com` (carte de la page
  Contact) ; images `https:` ; l’origine de l’outil de mesure d’audience
  (Plausible ou Matomo) n’est ajoutée qu’à la réponse qui le charge, et
  seulement si l’outil est configuré.

## Fichiers déposés

| Type | Règles |
|---|---|
| Images | JPEG, PNG, WebP ; 2 Mo ; vérification réelle de l’image ; nom aléatoire |
| PDF (brochures, maquettes, pièces jointes d’articles) | 10 Mo ; signature `%PDF-` vérifiée ; nom aléatoire |

Stockage par site : `public/uploads/sites/{slug}/{module}/`. Exécution de
scripts bloquée par `public/uploads/.htaccess` (Apache) et
`deploy/nginx/uploads-security.conf` (Nginx).

## Formulaire de contact

- Validation serveur ; champ piège anti-robot.
- Limites : un message par adresse e-mail toutes les 120 secondes, vingt
  messages par adresse IP et par heure.
- Messages rangés par site (`contact_messages`), suppression logique.
- Notification e-mail facultative (`CONTACT_NOTIFICATION_*`) ; les journaux
  ne contiennent jamais de secret SMTP.

## Liens saisis dans l’administration

Les champs « lien » n’acceptent qu’un chemin du site (`/…`, mais pas
`//…`), une ancre (`#…`) ou une adresse `http(s)://` valide ; tout autre texte
est refusé. Le sélecteur de page ne fait que remplir ce champ.

## Secrets

- `.env` est ignoré par Git ; `.env.example` ne contient que des valeurs
  d’exemple. Ne jamais copier un `.env` d’une instance à l’autre.
- Les mots de passe locaux vivent dans `LOCAL_CREDENTIALS.md` (ignoré par
  Git) ; aucune valeur sensible dans la documentation, les journaux ou les
  tickets.

## Accessibilité et affichage mobile

Présent dans le code et vérifié par
`tests/unit/AccessibilityResponsiveTest.php` :

- lien d’évitement, focus visible (`:focus-visible`), respect de
  `prefers-reduced-motion` ;
- cibles tactiles d’au moins 44 px (menus, boutons, options du sélecteur
  de page) ;
- menu public replié sous 1200 px, menu d’administration repliable sous
  992 px, tableaux dans `table-responsive` ;
- le sélecteur de page est un champ `combobox` relié à une liste
  `listbox`, utilisable au clavier (flèches, Entrée, Échap, Tab) ;
- couleurs de faculté assombries automatiquement pour garder un contraste
  suffisant sur les boutons et les textes colorés.

Largeurs contrôlées dans un navigateur : 320, 375, 768, 1024 et 1440 px,
sans débordement horizontal sur les pages publiques, le tableau de bord, les
listes et la superadministration.

## Critères avant livraison

- [ ] `php spark app:production-check --strict` sans erreur (voir
      [DEPLOIEMENT.md](DEPLOIEMENT.md)).
- [ ] `CI_ENVIRONMENT=production`, `app.baseURL` en HTTPS réel,
      `forceGlobalSecureRequests`, `DBDebug=false`, clé de chiffrement
      propre à l’instance, `proxyIPs` derrière un proxy.
- [ ] Le serveur web ne sert que `public/` ; `writable/` inaccessible.
- [ ] Textes « à remplacer » et mentions légales génériques remplacés.
- [ ] Aucun débordement horizontal sur téléphone avec les contenus réels.
- [ ] Navigation au clavier et focus visible sur liens, boutons et champs.
- [ ] Contrastes vérifiés avec la couleur et les images finales de la
      faculté.
- [ ] Sauvegardes de la base et de `public/uploads/` planifiées.

## Limites

- Contrôles faits avec Chromium ; Safari, Firefox et appareils physiques
  restent à vérifier.
- Les réglages réels de production ne sont pas vérifiables depuis le dépôt.
