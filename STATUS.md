# STATUS.md — État du projet

> Ce fichier décrit un **état**, pas un journal de conversation. Mettre à jour
> après chaque étape importante ; ne pas y accumuler l'historique complet des
> échanges.

## Current version

`0.1.1` — publiée sur <https://github.com/maxevstudio/nook/releases>
(ZIP généré par la CI, distribué aux sites via l'updater GitHub).

## Current milestone

**V1 fonctionnelle — cœur complet livré.** Les Milestones A à E (voir
PROJECT.md « Logique de travail ») sont terminés : modèle de données
versionné, wizard 7 étapes fonctionnel, scanner réel, 7 services V1, base de
connaissance des cookies exploitée, bannière/centre de préférences/blocage
réel côté front, dashboard à 6 onglets. Restent des raffinements de
Milestone F/G (voir « Next » ci-dessous) avant une release candidate.

## Completed

### Documentation et fondations (session précédente)

- Documentation permanente : `AGENTS.md`, `PROJECT.md`, `ARCHITECTURE.md`,
  `RELEASE.md`, `CHANGELOG.md`, `README.md`, ce fichier.
- Squelette WordPress du plugin, autoloader PSR-4 maison, build à deux
  entrées (`admin`/`frontend`) via `@wordpress/scripts`.
- Base de connaissance locale des cookies (`data/cookies.json`, 2266
  entrées, 355 services, licence Apache-2.0 attribuée).
- Script de release (`bin/release.mjs`).

### Milestone A — Modèle de données + wizard (cette session)

- Schéma de configuration complet et versionné (`Nook\Database\Settings`,
  voir ARCHITECTURE.md §3.6) : `schemaVersion`, `setupCompleted`, `site`,
  `consent`, `banner`, `policy`, `scanner`, `services`, `cookies`, `wizard`,
  `debug`.
- Mécanisme de migration de schéma (`Nook\Database\Migrations`), prêt pour
  une V2 sans qu'il faille réinventer l'architecture.
- **Correction d'un bug latent** : `sanitize_key()` de WordPress mettait les
  clés en minuscules, ce qui aurait corrompu toute clé camelCase envoyée par
  le wizard. Remplacé par un nettoyage de clé préservant la casse, avec test
  de non-régression.
- `Settings::merge()` : une liste séquentielle (ex. `wizard.completedSteps`)
  est désormais remplacée entièrement plutôt que fusionnée par index
  (`array_replace_recursive()` natif aurait été incorrect ici).
- Wizard 7 étapes réellement fonctionnel (`Wizard.jsx` + `pages/wizard/*`) :
  navigation avant/arrière, barre de progression cliquable (étapes
  complétées), sauvegarde à chaque changement d'étape, reprise fidèle après
  rafraîchissement, écran de finalisation avec récapitulatif.
- Dashboard initial puis complet (voir Milestone F ci-dessous).

### Milestone B — Scanner + Open Cookie Database (cette session)

- Scanner serveur réel (`Nook\Scanner\Scanner`, `UrlDiscovery`) : crawl
  limité (`wp_remote_get`, timeout 8s, 20 URLs max), extraction
  scripts/iframes via `DOMDocument` (repli regex), cookies HTTP réellement
  observés, verrou anti-concurrence (transient).
- `Nook\Database\CookieDatabase` : lookup exact/glob/préfixe avec seuil
  anti-faux-positifs (préfixe ≥ 4 caractères), chargement paresseux mis en
  cache.
- `Nook\Database\ScanResults` : stockage séparé des résultats bruts
  (`nook_scan_results`), pour ne jamais alourdir `nook_settings`.
- Endpoints REST `/scan` (verrouillé, synchrone) et `/scan/status`.
- Endpoint REST `/cookies` (liste fusionnée détection + corrections
  manuelles) et `/cookies/:id` (correction/ajout manuel) —
  `Nook\REST\CookiesController`, avec conservation garantie des corrections
  manuelles lors d'un re-scan (testé).
- Page « Cookies » du dashboard (recherche, filtres catégorie/statut,
  édition inline).

### Milestone C — Services (cette session)

- 7 services V1 complets : Google Analytics, Google Tag Manager, Google
  Ads, Meta Pixel, YouTube, Vimeo, Google Maps (`includes/Services/`),
  chacun avec domaines, motifs de script/iframe, cookies connus.
- `Nook\Services\ServiceState` : calcul d'état partagé (activé, catégorie,
  statut) entre l'API REST des services et le blocage front — logique non
  dupliquée.
- Endpoint REST `/services` (registre + détection + corrections) et
  `/services/:slug` (confirmer/corriger/activer, ou créer un service
  personnalisé).
- Étape « Services » du wizard + page « Services » du dashboard (avec
  formulaire d'ajout de service personnalisé).

### Milestone D/E — Bannière, préférences, blocage réel (cette session)

- Runtime public complet (`src/frontend/`) : bannière (Tout accepter/Tout
  refuser/Personnaliser à égalité visuelle, voir AGENTS.md règle 12),
  centre de préférences (focus trap, Échap, catégories individuelles),
  stockage du consentement (cookie first-party + miroir localStorage),
  petite API d'événements (`nook:consent-ready`/`consent-updated`/
  `category-enabled`/`category-disabled`).
- **Blocage réel** (`Nook\Frontend\ContentFilter`, `Blocklist`,
  `Placeholder`) : réécriture par expressions régulières ciblées de la
  sortie HTML bufferisée (jamais un aller-retour `DOMDocument` →
  `saveHTML()`, trop risqué pour la page entière). Scripts externes
  neutralisés (`type="text/plain"` + `data-nook-src`), scripts inline
  neutralisés par signature, iframes remplacées par un placeholder
  accessible. Désambiguïsation des services partageant un domaine (ex. GA
  vs GTM sur `googletagmanager.com`) en testant les motifs spécifiques
  avant les domaines génériques.
- Retrait de consentement : rechargement de page si une catégorie déjà
  active est révoquée (les scripts déjà exécutés ne peuvent pas tous être
  arrêtés de façon fiable dynamiquement — comportement documenté et
  volontaire), pas de rechargement pour un octroi additif (clic sur
  placeholder ou ajout de catégorie).
- Shortcodes `[nook_cookie_policy]` (dynamique, groupé par catégorie) et
  `[nook_cookie_settings]`, + lien de pied de page automatique et
  désactivable (`banner.footerLink`).
- Endpoint REST public `/nook/v1/public-config`, et injection inline de la
  configuration (`window.nookFrontend`) pour éviter un aller-retour REST
  supplémentaire sur chaque page.
- Bundle front vérifié : ~9,5 Ko JS minifié + ~2,4 Ko CSS, **aucune copie de
  React** (seul `wp-i18n` externalisé pour les libellés fixes de l'UI).

### Milestone F (partiel) — Dashboard complet

- Dashboard à 6 onglets (`TabPanel`) : Vue d'ensemble, Cookies, Services,
  Bannière, Politique, Paramètres. `BannerPage`/`PolicyPage` réutilisent
  directement les composants du wizard (pas de duplication). Page
  Paramètres : durée de consentement, mode debug, réinitialisation de
  configuration (confirmation requise).

### Tests et vérifications

- **60 tests PHP** (`php tests/php/run.php`, framework maison sans
  dépendance) couvrant : `Settings`, `Migrations`, `CategoryRegistry`,
  `CookieDatabase`, `ServiceRegistry`, `ServiceState`, `Scanner` (avec
  fixtures HTTP simulées), `Blocklist`, **`ContentFilter`** (blocage réel de
  scripts/iframes — la fonctionnalité la plus critique du projet, testée en
  détail : neutralisation GA4, désambiguïsation GA/GTM, script inline,
  placeholder YouTube, non-régression sur contenu sans rapport),
  `SettingsController` (dont le bug historique de casse des clés),
  `ScanController`, `ServicesController`, `CookiesController`, et un test de
  fumée du bootstrap complet du plugin.
- `npm run build`, `npm run lint:js`, `npm run lint:css` : verts.
- Vérification manuelle : aucune copie de React dans `build/frontend/`,
  noms de fichiers CSS générés vérifiés (`style-index.css`, pas `index.css`
  — correction appliquée dans `Assets.php`). Pipeline de release
  (`npm run release`) retesté avec succès dans une copie temporaire du
  dépôt (`--version=0.9.0`) : ZIP propre généré, structure vérifiée
  (`unzip -l`).

### Distribution GitHub et mises à jour automatiques

- Dépôt public `maxevstudio/nook` (branche `main`), CI GitHub Actions
  (lint, tests PHP, build) et workflow de release sur tag `v*`.
- `Nook\Updater\GitHubUpdater` : les sites installés reçoivent les
  nouvelles versions via l'écran natif « Mises à jour » (voir
  ARCHITECTURE.md §15, RELEASE.md « Publier une version »).
- 73 tests PHP.

## In progress

**Test en conditions réelles sur le site de Mathieu Panciatici** (copie
locale MAMP, `localhost:8888/mathieupanciatici`, WordPress 7.1.3,
Elementor/Elementor Pro, SimpleTrad). Wizard parcouru de bout en bout,
Nook activé, page « Politique de cookies » créée. Validé côté visiteur :
bannière, « Tout refuser », placeholder puis « Autoriser et afficher » sur
les vidéos YouTube chargées au clic, aucune requête YouTube avant
consentement, lien de préférences en pied de page.

Corrigé grâce à ce test (non encore publié en release) : chargement des
réglages admin (wizard qui plantait), résumé du scan, blocage des iframes
créées en JavaScript, scripts inline (JSON-LD, embeds), avertissement Google
Fonts.

Présentation revue à la demande de l'auteur (minimaliste) : bannière
compacte en bas à gauche, panneau glissant sur mobile, pastille cookie
après le choix. Vérifié sur ordinateur et en 375 px. Le site de Mathieu est
réglé en « Compacte », rayon 16 px, sans lien de pied de page.

**Décision en attente — style des boutons** : l'auteur souhaite garder
« Tout accepter » plus mis en avant que « Tout refuser » (éventuellement
couleur profonde vs plus terne), après avoir regardé ce que font les autres
CMP. Ceci s'écarte d'AGENTS.md règle 12 et des recommandations CNIL
(même niveau, même forme) : à trancher et, le cas échéant, à documenter.

Tous les correctifs et la nouvelle présentation issus de ce test sont
publiés en **0.1.1**, après validation par l'auteur sur le site de Mathieu.

## Next

Par ordre de priorité suggéré pour finaliser la V1 (voir aussi PROJECT.md
« Logique de travail », Milestones F/G) :

1. **Tests JS** — aucun test JS n'existe encore (`tests/js/` ne contient
   qu'un README). `@wordpress/scripts test-unit-js` (version installée)
   requiert Vitest 5 + Vite 8. **Tentative d'installation échouée** dans cet
   environnement de développement : `npm install --save-dev vitest@^5
   vite@^8` provoque une erreur `Cannot read properties of null (reading
   'edgesOut')` (bug connu de résolution de peer dependencies dans
   `@npmcli/arborist`, npm 10.9.2) ; `--legacy-peer-deps` contourne le crash
   mais casse ensuite `npm run lint:js` (eslint-plugin-jsdoc ne retrouve
   plus son eslint imbriqué). La tentative a été annulée proprement
   (`git checkout -- package.json package-lock.json && npm ci`) pour ne pas
   laisser le dépôt dans un état instable — voir `tests/js/README.md`.
   Prioriser le store (`src/admin/store/index.js`) et
   `consent-storage.js`/`isConsentValid()` une fois l'outillage réparé
   (npm plus récent, ou versions exactes épinglées plutôt que des plages
   `^`).
2. **Google Consent Mode** — non implémenté (hors priorité V1 explicite,
   voir PROJECT.md §50). À réévaluer seulement si un besoin réel émerge.
3. **Bloc Gutenberg** équivalent à `[nook_cookie_settings]` — mentionné
   comme extension possible dans PROJECT.md, pas prioritaire V1.
4. **Incrémentation automatique de `consent.configVersion`** — actuellement
   manuelle (champ existant, logique de comparaison
   `isConsentValid()` déjà centralisée et testée) ; une détection
   automatique des changements substantiels (nouveau service marketing
   activé, etc.) serait un raffinement V1.1.
5. **PHPCS** (`WordPress Coding Standards`) — envisager une configuration
   dev-only (`composer require-dev`) pour automatiser la vérification des
   conventions PHP au-delà de la relecture manuelle.
6. **Vérification en conditions réelles** — aucun test n'a encore été fait
   sur une installation WordPress complète (Docker/wp-cli indisponibles
   dans cet environnement de développement). Recommandé avant une release
   candidate : installer sur un WordPress réel (idéalement avec Elementor
   et WooCommerce actifs) et valider les critères d'acceptation de
   PROJECT.md §78-84 (première visite, refus, acceptation,
   personnalisation, modification, scanner).
7. **`npm run release`** n'a pas été re-testé dans cette session (le test
   précédent, en copie temporaire du dépôt, reste documenté dans
   RELEASE.md) : à revalider avant de taguer une version, notamment parce
   que le nombre de fichiers PHP/JS a significativement augmenté.

## Known issues

- **`/usr/bin/git` (fourni par Xcode complet) reste bloqué** sur la machine
  de développement (licence Xcode non acceptée). Utiliser
  `/Library/Developer/CommandLineTools/usr/bin/git` directement, ou
  `export PATH="/Library/Developer/CommandLineTools/usr/bin:$PATH"` en
  début de session.
- Aucun environnement WordPress réel (Docker/wp-cli) n'était disponible
  dans cet environnement pour une vérification bout-en-bout : toute la
  validation s'est faite via le test de fumée PHP (`tests/php/`) simulant
  les fonctions WordPress utilisées, plus `npm run build`/`lint`. Une
  vérification manuelle sur un vrai WordPress est fortement recommandée
  avant publication (voir « Next » point 6).
- `consent.configVersion` doit être incrémenté manuellement pour l'instant
  (voir « Next » point 4).

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
- **Blocage front par réécriture regex de la sortie bufferisée**, jamais par
  `DOMDocument`/`saveHTML()` (risque de corruption de page) — voir
  ARCHITECTURE.md §3.4 et §14.
- **Tests PHP sans PHPUnit** : framework maison minimal
  (`tests/php/framework.php` + stubs WordPress dans `tests/php/bootstrap.php`),
  cohérent avec l'absence de dépendance Composer obligatoire au runtime.
- **`@wordpress/i18n` ajouté au bundle front public**, externalisé (jamais
  bundlé), pour les quelques libellés fixes du centre de préférences — voir
  ARCHITECTURE.md §9.
