# Revue Sécurité

Dernière actualisation documentaire : 11 août 2026.

Cette revue décrit l'état réel du repository. Elle ne remplace pas un audit externe avant mise en production.

## Synthèse

- Authentification et autorisations : CodeIgniter Shield.
- Administration protégée par `session` et `permission:admin.access`.
- CSRF global activé.
- Secure headers activés.
- CSP configurée et activée dans les fichiers versionnés; la valeur runtime reste à contrôler dans `.env`.
- Uploads d'images validés et stockés sous `public/uploads/sites/{slug}/...`.
- Contrôle production disponible avec `php spark app:production-check`.
- Risque majeur restant : un fichier `env` suivi par Git peut contenir des valeurs sensibles.

## Authentification Et Permissions

Groupes configurés :

- `superadmin`
- `admin`
- `editor`

Permissions principales :

- `admin.access`
- `home.manage`
- `news.manage`
- `events.manage`
- `programmes.manage`
- `staff.manage`
- `research.manage`
- `alumni.manage`
- `pages.manage`
- `messages.manage`
- `users.manage`
- `settings.manage`
- `sites.manage`

La gestion utilisateurs protège contre :

- retrait de son propre accès admin;
- désactivation du dernier superadministrateur;
- modification d'un compte superadmin par un acteur non superadmin;
- attribution non autorisée de `sites.manage`.

## CSRF, Sessions Et Headers

- `csrf` est configuré dans les filtres globaux avant requête.
- `secureheaders` est configuré après réponse.
- Les formulaires de modification utilisent des méthodes POST explicites.
- Aucune suppression administrative documentée ne passe par GET.
- Les sessions utilisent la configuration CodeIgniter standard et doivent être durcies par l'environnement de production.

## Content Security Policy

`app/Config/App.php` et `.env.example` indiquent `CSPEnabled = true`. Un fichier `.env` local ignoré peut toutefois surcharger cette valeur; `app:production-check` contrôle donc la valeur runtime.

La politique CSP bloque notamment :

- `object-src 'none'`
- `frame-ancestors 'self'`
- scripts en attribut via `script-src-attr 'none'`

Les actifs frontaux (Bootstrap 5.3.3, Bootstrap Icons 1.11.3, police Inter) sont **auto-hébergés** dans `public/assets/vendor/` : aucune dépendance CDN. La CSP n'autorise que `'self'` pour les scripts, styles et polices. Seule exception fonctionnelle : `frame-src https://www.google.com` pour l'intégration optionnelle d'une carte Google Maps sur la page contact.

## Uploads

`MediaService` applique les règles suivantes :

- extensions autorisées : `jpg`, `jpeg`, `png`, `webp`;
- MIME autorisés : JPEG, PNG, WebP;
- taille maximale : 2 Mo;
- vérification image via métadonnées;
- nom aléatoire généré côté serveur;
- stockage par site et module.

Protections présentes :

- `public/uploads/.htaccess` pour Apache;
- `deploy/nginx/uploads-security.conf` pour Nginx.

## Messages De Contact

- Validation serveur du formulaire public.
- Stockage site-scopé dans `contact_messages`.
- Notification email facultative via variables `CONTACT_NOTIFICATION_*`.
- Les logs ne doivent jamais contenir de secret SMTP.

## Secrets Et Environnement

- `.env.example` contient des placeholders.
- `.env` est ignoré par Git.
- `env` est présent dans le repository suivi par Git et peut contenir des valeurs sensibles configurées.

Action recommandée :

- retirer `env` du suivi Git;
- vérifier s'il a été poussé ou partagé;
- faire tourner la clé de chiffrement, le mot de passe base et tout autre secret exposé;
- conserver uniquement `.env.example` comme modèle public.

Aucune valeur sensible ne doit être copiée dans la documentation, les logs ou les tickets.

## Production

Avant livraison :

- `CI_ENVIRONMENT=production`;
- `app.baseURL` en HTTPS réel;
- `app.forceGlobalSecureRequests=true`;
- `database.default.DBDebug=false`;
- `encryption.key` réel et secret;
- `app.proxyIPs` renseigné si reverse proxy;
- SMTP complet si notifications activées;
- serveur web pointant vers `public/` uniquement;
- accès direct à `writable/` impossible depuis le web;
- sauvegardes et rotation des logs définies.

## Commande De Contrôle

```bash
php spark app:production-check
php spark app:production-check --strict
```

Sans `--strict`, les erreurs bloquent et les avertissements restent informatifs. Avec `--strict`, les avertissements deviennent bloquants, par exemple `proxyIPs` vide selon le contexte.

## Risques Résiduels

- Secrets potentiellement versionnés dans `env`.
- Validation navigateur manuelle finale encore nécessaire.
- Aucune dépendance CDN restante (actifs auto-hébergés).
- Paramètres de production réels non vérifiables depuis le repository seul.
