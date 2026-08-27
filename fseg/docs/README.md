# Documentation

Cet index indique quel document lire selon le besoin. Ce dépôt est le
**modèle (template)** de la plateforme ; l'état actuel du système est décrit
dans [ARCHITECTURE.md](ARCHITECTURE.md), et les règles opérationnelles pour
agents dans [../AGENTS.md](../AGENTS.md).

## Cible Et Déploiement

- [Architecture actuelle](ARCHITECTURE.md) : instances, isolation, accès,
  résolution du site, front auto-hébergé, qualité.
- [Mise en place du modèle](TEMPLATE_SETUP.md) : procédure de copie manuelle
  pour créer une instance facultaire ou l'instance superadministration.
- [Déploiement multi-dossiers](MULTI_FOLDER_DEPLOYMENT.md) : un dossier par
  faculté + un dossier superadmin, base partagée, fonctionnement technique.
- [Guide d'installation et déploiement](GUIDE_INSTALLATION_DEPLOIEMENT.md) :
  installation locale, base, production.
- [Guide de déploiement cPanel](GUIDE_DEPLOIEMENT_CPANEL.md) : hébergement
  mutualisé Apache/cPanel, `public/`, `.env`, base, migrations, dépannage.

## Architecture

- [Architecture multi-sites](ARCHITECTURE_MULTI_SITES.md) : `sites`,
  `user_sites`, résolution du site actif et isolation `site_id`.
- [Architecture bilingue](ARCHITECTURE_BILINGUE.md) : détection de locale,
  traductions `content_translations` et fallback français.

## Références

- [Base de données](05-BASE-DE-DONNEES.md) : schéma logique issu des migrations.
- [Audit technique du 25 août 2026](AUDIT_2026-08-25.md) : bugs corrigés,
  suspicions vérifiées, points notés (document daté).
- [Revue sécurité](SECURITY_REVIEW.md) : configuration sécurité, risques et production.
- [Revue accessibilité responsive](ACCESSIBILITY_RESPONSIVE_REVIEW.md) :
  constats et limites de validation manuelle.

## Guides Utilisateurs

- [Guide administrateur](GUIDE_ADMINISTRATEUR.md) : écrans et actions du back-office.
- [Guide utilisateur client](GUIDE_UTILISATEUR_CLIENT.md) : utilisation du site public.
- [Cahier des charges](CAHIER_DES_CHARGES.md) : besoins et critères
  d'acceptation d'origine (document de référence client).

## Développement

- [Contribuer](../CONTRIBUTING.md) : conventions, tests, sécurité et PR.
- [Tests](../tests/README.md) : commandes, base de test dédiée (le bootstrap
  la supprime et la recrée).
- [Changelog](../CHANGELOG.md) : changements visibles sans dates inventées.
