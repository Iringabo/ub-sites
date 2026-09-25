# Journal des changements

Ce fichier suit les changements visibles du projet. Les dates ne sont
ajoutées que lorsqu’elles sont vérifiables.

## [Non publié]

- Cookie de langue public par instance (`site_locale_{slug}`).
- `X-Forwarded-Host` ignoré sans `app.proxyIPs` (admin et public).
- Échec visible des paramètres si l’enregistrement échoue ; 404 HTML pour
  toutes les pages manquantes ; instance facultaire en échec fermé si la
  base est indisponible.
- Scripts de copie : cookies session/remember, refus de `--link-vendor`,
  `sync-instances.sh`.
- Remember-me désactivé sur la superadministration.

- Administration regroupée Accueil / Pages du site / Messages / Comptes /
  Identité ; l’administrateur de faculté gère identité, messages, et peut
  créer uniquement un **éditeur** de *sa* faculté.
- Superadmin : édition in situ de la faculté choisie ; plus de création de
  site depuis l’écran Facultés ; sites `template` / `demo` exclus.
- Cookies de session par instance (`session.cookieName`).
- Documentation alignée sur cet état : audits datés et copies « créer un
  site » retirés des instances facultaires / superadmin.

## [Historique de reprise]

Reprise du starter CodeIgniter : multi-site `site_id`, Shield, messages,
utilisateurs, accueil dynamique, revues sécurité et accessibilité.
