# Nook

Gestionnaire de consentement aux cookies léger, moderne, indépendant et
WordPress-natif.

> Installer → analyser → configurer → publier.

Auteur : **Maxev**

Ce README s'adresse aux développeurs qui travaillent sur le plugin. Pour la
philosophie produit, l'architecture technique et l'état d'avancement réel,
voir dans l'ordre :

1. [AGENTS.md](AGENTS.md) — règles non négociables du projet.
2. [PROJECT.md](PROJECT.md) — cahier des charges produit.
3. [ARCHITECTURE.md](ARCHITECTURE.md) — architecture technique détaillée.
4. [STATUS.md](STATUS.md) — état d'avancement réel du projet.
5. [RELEASE.md](RELEASE.md) — workflow de publication.
6. [CHANGELOG.md](CHANGELOG.md) — historique des changements.

**Ce dépôt est la source de vérité du projet, pas l'historique d'une
conversation.** Toute session de développement (humaine ou IA) doit lire ces
fichiers avant de modifier l'architecture.

## Prérequis

- **Node.js** ≥ 20 (testé avec Node 23) et npm.
- **PHP** ≥ 7.4 (recommandé : 8.1+ ; WordPress recommande officiellement PHP
  8.3+ pour les nouvelles installations).
- **WordPress** ≥ 6.6 (nécessaire pour le script `react-jsx-runtime` fourni
  par le cœur de WordPress).
- Une installation WordPress locale pour tester le plugin (`wp-env`, Local,
  MAMP, etc. — au choix, non imposé par ce dépôt).

## Installation (développement)

```bash
npm install
```

Copiez ou liez ensuite le dossier de ce dépôt dans
`wp-content/plugins/nook` de votre installation WordPress locale, puis
activez le plugin **Nook** depuis **Extensions**.

## Commandes de build

```bash
npm start          # build en mode watch (développement)
npm run build       # build de production (build/admin/, build/frontend/)
npm run lint:js      # lint des sources JS/JSX
npm run lint:css     # lint des styles CSS
npm run format       # formatage automatique (prettier via wp-scripts)
npm test             # tests JS (à écrire, voir STATUS.md)
php tests/php/run.php # tests PHP (framework maison, aucune dépendance)
npm run release      # génère une release complète (voir RELEASE.md)
```

Le build produit deux entrées **séparées** (voir
[ARCHITECTURE.md](ARCHITECTURE.md) §2 et §13) :

- `build/admin/` — application React de l'administration.
- `build/frontend/` — runtime public en JavaScript natif (sans React).

`@wordpress/scripts` externalise automatiquement `react`, `react-dom` et les
paquets `@wordpress/*` : le build ne contient jamais de copie de React (voir
[AGENTS.md](AGENTS.md), règle 4).

## Structure du projet

```
nook/
├── nook.php              # Bootstrap du plugin
├── uninstall.php
├── includes/             # PHP (namespace Nook\)
│   ├── Admin/            # Menu + enqueue de l'admin React
│   ├── REST/             # Contrôleurs REST (nook/v1) : settings, scan,
│   │                     # services, cookies, public-config
│   ├── Scanner/          # Scanner serveur (crawl, découverte d'URLs)
│   ├── Consent/          # Catégories de consentement
│   ├── Services/         # Registre de services tiers (un fichier = un service)
│   ├── Database/         # Réglages, migrations, résultats de scan, cookies
│   └── Frontend/         # Assets, blocage réel (ContentFilter), shortcodes
├── src/
│   ├── admin/            # Application React (wizard 7 étapes, dashboard 6 onglets)
│   └── frontend/         # Runtime public (JS natif, bannière/préférences/blocage)
├── data/
│   └── cookies.json      # Base locale de cookies (Open Cookie Database, Apache-2.0)
├── build/                # Généré par `npm run build`
├── languages/            # Fichiers de traduction
├── tests/
│   ├── php/              # Tests PHP (framework maison, `php tests/php/run.php`)
│   └── js/               # Tests JS (à écrire)
├── bin/release.mjs       # Script de release
└── AGENTS.md / PROJECT.md / ARCHITECTURE.md / STATUS.md / RELEASE.md / CHANGELOG.md
```

Détail complet dans [ARCHITECTURE.md](ARCHITECTURE.md).

## Tests

- **PHP** : `php tests/php/run.php` — 60 tests, framework maison sans
  dépendance (pas de PHPUnit ni de Composer requis à l'exécution). Couvre le
  modèle de données, les migrations, le scanner, les services, les cookies
  et, en particulier, le blocage réel de scripts/iframes
  (`tests/php/ContentFilterTest.php`).
- **JS** : `npm test` (basé sur `@wordpress/scripts test-unit-js`, qui
  requiert Vitest 5 + Vite 8). Aucun test n'est encore écrit — voir
  [STATUS.md](STATUS.md) et `tests/js/README.md` pour les prochaines étapes
  et une mise en garde sur un bug npm rencontré lors d'une tentative
  d'installation.

## Release

```bash
npm run release
npm run release -- --version=1.2.0
```

Génère un ZIP WordPress propre et installable dans `dist/nook-<version>.zip`.
Détail complet du processus dans [RELEASE.md](RELEASE.md).

## Licence

GPLv2 ou ultérieure (voir [LICENSE](LICENSE)). La base de cookies
`data/cookies.json` est dérivée de l'[Open Cookie
Database](https://github.com/jkwakman/Open-Cookie-Database) sous licence
Apache-2.0 (voir `data/OPEN-COOKIE-DATABASE-LICENSE` et
[ARCHITECTURE.md](ARCHITECTURE.md) §5).
