# Changelog

Toutes les modifications notables de Nook sont documentées dans ce fichier.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/) et
le projet suit le [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Blocage des iframes créées en JavaScript (ex. lecteur YouTube chargé au
  clic) : petit garde chargé dans le `<head>` qui met l'URL de côté avant
  toute requête et affiche le placeholder « Autoriser et afficher ».
- Bannière « Compacte » (nouvelle disposition par défaut) : carte flottante
  minimaliste en bas à gauche au lieu d'une barre pleine largeur.
- Mobile : bannière et centre de préférences en panneau qui glisse depuis
  le bas de l'écran.
- Pastille « Préférences cookies » en bas à gauche après le choix (icône
  cookie qui s'allonge au survol), activable dans l'onglet Bannière. Le
  lien de pied de page devient optionnel (désactivé par défaut) et
  réglable lui aussi.
- Le scan détecte les services dont l'URL d'embed n'apparaît que dans un
  script inline, et signale Google Fonts (avertissement avec la marche à
  suivre pour héberger les polices localement).

### Fixed
- Le filtre serveur ne neutralise plus les données structurées JSON-LD,
  les scripts de Nook, ni les scripts qui ne font que construire une
  iframe d'embed (bloquée désormais par le garde).
- Boutons de Nook déformés par les styles globaux de certains thèmes
  (bordure rose, coins carrés, fond au survol avec Hello Elementor).
- Récapitulatif du wizard : « Page créée » au lieu de « va être créée »
  quand la page de politique existe déjà.
- Admin : les réglages n'étaient jamais chargés (resolver `getSettings`
  jamais déclenché, puis Promise stockée au lieu des données), ce qui
  faisait planter le wizard dès « Commencer la configuration » et aurait
  empêché l'affichage du dashboard. Constaté en conditions réelles.
- Étape « Analyse » : le résumé du scan ne s'affichait pas après l'analyse
  (`lastScanAt` jamais mis à jour côté admin). `/scan` renvoie désormais
  l'état `scanner` complet.

## 0.1.0 — 15 octobre 2026

### Added
- Mises à jour automatiques depuis les releases GitHub publiques
  (`maxevstudio/nook`) via l'écran natif « Mises à jour » de WordPress,
  désactivables par filtre (`nook_github_updater_enabled`).
- Workflows GitHub Actions : CI (lint, tests PHP, build) et publication
  automatique du ZIP à chaque tag `v*`.
- Documentation permanente du projet : `AGENTS.md`, `PROJECT.md`,
  `ARCHITECTURE.md`, `STATUS.md`, `RELEASE.md`, `README.md`.
- Squelette du plugin WordPress (`nook.php`, `uninstall.php`, arborescence
  `includes/`).
- Système de build basé sur `@wordpress/scripts` (entrées admin/frontend
  séparées).
- Base de connaissance locale des cookies (`data/cookies.json`), dérivée de
  l'Open Cookie Database (licence Apache-2.0, attribution conservée).
- Script de release (`bin/release.mjs`) et commande `npm run release`.
- Modèle de configuration complet et versionné (`nook_settings`), avec
  mécanisme de migration de schéma.
- Assistant de configuration (wizard) 7 étapes entièrement fonctionnel :
  Site, Analyse, Services, Consentement, Design, Politique de cookies,
  Finalisation — navigation avant/arrière, sauvegarde et reprise après
  rafraîchissement.
- Scanner serveur réel (crawl limité, détection de scripts/iframes/cookies
  HTTP, verrou anti-concurrence) et base de connaissance des cookies
  interrogeable (correspondance exacte/glob/préfixe).
- 7 services V1 : Google Analytics, Google Tag Manager, Google Ads, Meta
  Pixel, YouTube, Vimeo, Google Maps.
- API REST complète : réglages, réinitialisation, scan, services, cookies,
  configuration publique.
- Bannière de consentement, centre de préférences et blocage réel des
  scripts/iframes tiers avant consentement (runtime front public léger,
  sans React).
- Shortcodes `[nook_cookie_policy]` et `[nook_cookie_settings]`, lien de
  préférences en pied de page (désactivable).
- Tableau de bord à 6 onglets : Vue d'ensemble, Cookies, Services, Bannière,
  Politique, Paramètres.
- Suite de tests PHP (60 tests, framework maison sans dépendance) couvrant
  le modèle de données, le scanner, les services, les cookies et — en
  particulier — le blocage réel de scripts/iframes.

### Changed
- Renommage du champ `configured` en `setupCompleted` dans le modèle de
  configuration, pour plus de clarté.

### Fixed
- Correction d'un bug de sanitation qui aurait mis en minuscules les clés
  camelCase du modèle de configuration (`sanitize_key()` natif de WordPress
  remplacé par un nettoyage préservant la casse).

## 0.1.0-dev — fondations

Version de travail initiale, non publiée. Voir [STATUS.md](STATUS.md) pour
l'état d'avancement détaillé.
