# Documentation

Ce dossier est le **modèle** de la plateforme. État du système :
[ARCHITECTURE.md](ARCHITECTURE.md). Règles agents : [../AGENTS.md](../AGENTS.md).

## Cible et déploiement

- [Créer un site facultaire](CREER_UN_SITE.md) : copier ce dossier, trois
  champs `.env`, deux commandes — guide pour un non-programmeur.
- [Architecture actuelle](ARCHITECTURE.md) : instances, isolation, accès,
  cookies de session, qualité.
- [Déploiement multi-dossiers](MULTI_FOLDER_DEPLOYMENT.md) : fonctionnement
  technique, rsync des copies.
- [Guide d’installation](GUIDE_INSTALLATION_DEPLOIEMENT.md) : machine neuve,
  base, production.
- [Guide cPanel](GUIDE_DEPLOIEMENT_CPANEL.md) : hébergement mutualisé.

## Architecture

- [Architecture multi-sites](ARCHITECTURE_MULTI_SITES.md) : `sites`,
  `user_sites`, `site_id`.
- [Architecture bilingue](ARCHITECTURE_BILINGUE.md) : locale et
  `content_translations`.

## Références

- [Base de données](05-BASE-DE-DONNEES.md)
- [Revue sécurité](SECURITY_REVIEW.md)
- [Revue accessibilité responsive](ACCESSIBILITY_RESPONSIVE_REVIEW.md)

## Guides

- [Guide administrateur](GUIDE_ADMINISTRATEUR.md) : trois zones, rôles,
  superadmin in situ.
- [Guide du site public](GUIDE_UTILISATEUR_CLIENT.md)
- [Cahier des charges](CAHIER_DES_CHARGES.md) : besoins d’origine (référence
  client, pas l’état du code).

## Développement

- [Contribuer](../CONTRIBUTING.md)
- [Tests](../tests/README.md)
- [Changelog](../CHANGELOG.md)
