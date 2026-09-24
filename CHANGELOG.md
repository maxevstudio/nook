# Changelog

Toutes les modifications notables de Nook sont documentées dans ce fichier.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/) et
le projet suit le [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Documentation permanente du projet : `AGENTS.md`, `PROJECT.md`,
  `ARCHITECTURE.md`, `STATUS.md`, `RELEASE.md`, `README.md`.
- Squelette du plugin WordPress (`nook.php`, `uninstall.php`, arborescence
  `includes/`).
- Application React d'administration minimale (`src/admin/`) affichant
  l'écran initial « Configurez le consentement de votre site ».
- Système de build basé sur `@wordpress/scripts` (entrées admin/frontend
  séparées).
- Base de connaissance locale des cookies (`data/cookies.json`), dérivée de
  l'Open Cookie Database (licence Apache-2.0, attribution conservée).
- Script de release (`bin/release.mjs`) et commande `npm run release`.

## 0.1.0-dev — fondations

Version de travail initiale, non publiée. Voir [STATUS.md](STATUS.md) pour
l'état d'avancement détaillé.
