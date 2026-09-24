# STATUS.md — État du projet

> Ce fichier décrit un **état**, pas un journal de conversation. Mettre à jour
> après chaque étape importante ; ne pas y accumuler l'historique complet des
> échanges.

## Current version

`0.1.0-dev` (aucune release publique n'a encore été taguée).

## Current milestone

**Fondations** — bootstrap WordPress, documentation permanente, architecture,
squelette du plugin, build, écran d'accueil minimal. Le wizard, le scanner, le
script blocking et le dashboard complets restent à construire.

## Completed

- Documentation permanente créée et cohérente entre elle : `AGENTS.md`,
  `PROJECT.md`, `ARCHITECTURE.md`, `RELEASE.md`, `CHANGELOG.md`, `README.md`,
  ce fichier.
- Recherche préalable effectuée : versions stables des packages
  `@wordpress/*` (scripts 36, components 41, element 6, data 10, i18n 5,
  icons 10, api-fetch 7), prérequis WordPress (≥ 6.6 pour le handle
  `react-jsx-runtime`), PHP (≥ 7.4, recommandé 8.1+), licence Apache-2.0 de
  l'Open Cookie Database vérifiée.
- Squelette WordPress du plugin : `nook.php` (en-tête, constantes, autoloader
  PSR-4 maison, garde-fou version PHP), `uninstall.php`.
- Arborescence `includes/` posée avec un exemple fonctionnel dans chaque
  module :
  - `Plugin.php` (orchestrateur), `Admin/Menu.php` (menu + enqueue React),
    `REST/SettingsController.php` (`GET`/`POST /nook/v1/settings`, permissions
    `manage_options`, sanitation récursive),
    `Database/Settings.php` (défauts + fusion + `update_option`),
    `Consent/CategoryRegistry.php` (4 catégories, filtre
    `nook/consent/categories`),
    `Services/ServiceDefinition.php` + `ServiceRegistry.php` +
    `GoogleAnalytics.php` (pattern « un service = une classe »),
    `Scanner/Scanner.php` (interface posée, logique non implémentée),
    `Frontend/Assets.php` (enqueue du runtime public).
- Vérifié par un test de fumée PHP (stubs de fonctions WP) : le bootstrap,
  l'autoloader, `Settings::get_defaults()`, `ServiceRegistry::get_services()`
  et `SettingsController` s'exécutent sans erreur fatale.
- Application React d'administration (`src/admin/`) : `index.js`, `App.jsx`,
  store `@wordpress/data` (`store/index.js`, resolver `getSettings` via
  `apiFetch`), pages `Onboarding.jsx` (écran initial « Configurez le
  consentement de votre site » + bouton « Commencer la configuration »),
  `Wizard.jsx` (coquille listant les 7 étapes, non fonctionnelle),
  `Dashboard.jsx` (coquille).
- **Aucune API expérimentale `@wordpress/components`** utilisée (le lint
  `@wordpress/no-unsafe-wp-apis` a détecté un premier essai avec
  `__experimentalHeading`/`__experimentalText` ; corrigé avec des balises
  HTML natives — décision cohérente avec AGENTS.md règle 2/3).
- Runtime public (`src/frontend/index.js`) : point d'entrée minimal, sans
  React, sans logique de bannière/blocage pour l'instant.
- Build à deux entrées (`webpack.config.js` personnalisé étendant la config
  par défaut de `@wordpress/scripts`) : `build/admin/index.js` (5,5 Ko
  minifié, aucune copie de React embarquée — vérifié) et
  `build/frontend/index.js`. `npm run build` et `npm run lint:js` passent
  sans erreur.
- Base de connaissance locale des cookies (`data/cookies.json`, 2266 entrées,
  355 services) générée à partir de l'Open Cookie Database (licence
  Apache-2.0, fichier de licence conservé dans
  `data/OPEN-COOKIE-DATABASE-LICENSE`, attribution documentée dans
  `ARCHITECTURE.md` §5).
- Script de release (`bin/release.mjs`, commande `npm run release`) :
  implémente les 12 étapes décrites dans `RELEASE.md` (lint, build,
  versionnage, changelog, packaging, vérification, ZIP via la commande
  système `zip`). **Testé avec succès en conditions réelles** dans une copie
  temporaire du dépôt (`--version=1.0.0`) : ZIP de 148 Ko généré, dossier
  racine `nook/`, aucun `node_modules/`/`.git/`/`src/`/`tests/`/`package.json`
  dans la distribution. Le dépôt de travail n'a pas été altéré par ce test.
- `readme.txt` (format WordPress.org) et `LICENSE` (GPLv2) créés.

## In progress

- Rien en cours d'écriture au moment de la rédaction de ce statut — la
  prochaine session peut reprendre directement sur la section « Next ».

## Next

Par ordre de priorité suggéré (voir aussi PROJECT.md « Priorités V1ᵃ ») :

1. **Assistant de configuration réel** — remplacer la coquille `Wizard.jsx`
   par les 7 étapes fonctionnelles (Site, Analyse, Services, Consentement,
   Design, Politique de cookies, Finalisation), avec sauvegarde de
   progression via `POST /nook/v1/settings`.
2. **Scanner** — implémenter `Nook\Scanner\Scanner::scan()` (crawl
   `wp_remote_get`, extraction scripts/iframes, confrontation à
   `data/cookies.json` et à `ServiceRegistry`), endpoints REST
   `/scan`, `/scan/status`, `/scan/results`.
3. **Services V1 restants** — `GoogleTagManager`, `GoogleAds`, `MetaPixel`,
   `YouTube`, `Vimeo`, `GoogleMaps` (même pattern que `GoogleAnalytics.php`).
4. **Bannière + centre de préférences** côté runtime public (`src/frontend/`)
   avec stockage du consentement navigateur (structure documentée dans
   ARCHITECTURE.md §6).
5. **Script blocking réel** (`includes/Frontend/`) : interception des
   `<script>`/`<iframe>` correspondant aux patterns de service.
6. **Shortcodes** `[nook_cookie_settings]` et `[nook_cookie_policy]`.
7. **Dashboard** complet (Vue d'ensemble, Cookies, Services, Bannière,
   Politique, Paramètres) en remplacement de la coquille `Dashboard.jsx`.
8. **Tests** : premiers tests PHPUnit (`tests/php/`, voir son README pour les
   priorités) et premiers tests JS (`tests/js/`, nécessite
   `npm install --save-dev vitest` avant de pouvoir exécuter
   `wp-scripts test-unit-js`).
9. **PHPCS** : envisager une configuration `WordPress Coding Standards` en
   dev-only (composer require-dev) pour automatiser la vérification des
   conventions PHP.

## Known issues

- **Git indisponible sur la machine de développement actuelle** : la licence
  Xcode Command Line Tools n'est pas acceptée
  (`sudo xcodebuild -license accept` nécessite une confirmation interactive
  impossible à automatiser ici), ce qui bloque `git`/`brew install git`. Le
  dépôt n'est donc **pas encore un dépôt git initialisé**. Une future session
  (ou l'utilisateur directement) devra exécuter `sudo xcodebuild -license
  accept` (ou installer un git indépendant de Xcode), puis `git init` et un
  premier commit. Aucun autre outil (npm, php, mkdir) n'est affecté.
- Le script `npm test` (`wp-scripts test-unit-js`) nécessite l'installation de
  Vitest (`npm install --save-dev vitest`) avant de pouvoir s'exécuter — non
  fait pour l'instant car aucun test n'existe encore (voir `tests/js/README.md`).
- Aucune suite PHPUnit n'existe encore (`tests/php/` ne contient qu'un README
  de priorités).
- Le scanner, le script blocking, la bannière/centre de préférences et 6 des
  7 services V1 restent à implémenter (voir « Next »).

## Important recent decisions

- **Autoloading** : PSR-4 maison dans `nook.php`, pas de dépendance Composer
  au runtime (le plugin doit rester installable par simple copie de dossier).
- **Build à deux entrées** distinctes (`admin`/`frontend`) via un
  `webpack.config.js` personnalisé qui étend la config par défaut de
  `@wordpress/scripts` plutôt que de s'appuyer sur la détection automatique
  d'entrées (pensée pour les blocs Gutenberg, pas adaptée ici).
- **Zéro API expérimentale** `@wordpress/components` : utilisation de balises
  HTML natives (`h1`, `p`) plutôt que `__experimentalHeading`/`__experimentalText`,
  conformément à AGENTS.md règle 2/3 et à la règle ESLint
  `@wordpress/no-unsafe-wp-apis`.
- **Génération du ZIP via la commande système `zip`** plutôt qu'une
  dépendance npm dédiée (`archiver`) : la version majeure actuellement publiée
  d'`archiver` (v8) a changé d'API de façon incompatible avec l'usage prévu ;
  `zip` est disponible nativement sur macOS/Linux et évite une dépendance
  supplémentaire pour un besoin simple.
- **Stockage du consentement 100% côté navigateur** en V1 (pas de preuve de
  consentement serveur), voir ARCHITECTURE.md §3.5 et §6.
- **`data/cookies.json`** est une transformation normalisée (pas une copie
  brute) de l'Open Cookie Database, avec mapping documenté des catégories
  d'origine vers les 4 catégories Nook (voir ARCHITECTURE.md §5).
- **`Requires at least: 6.6`** dans l'en-tête du plugin (plutôt que 6.5) car
  le handle de script `react-jsx-runtime` utilisé par le build
  `@wordpress/scripts` (JSX automatic runtime) n'est fiable qu'à partir de
  cette version du cœur WordPress.
