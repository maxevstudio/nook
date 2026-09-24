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
npm run format       # formatage automatique (prettier via wp-scripts)
npm test             # tests JS (à écrire, voir STATUS.md)
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
│   ├── REST/             # Contrôleurs REST (nook/v1)
│   ├── Scanner/          # Scanner serveur (cookies/scripts/services)
│   ├── Consent/          # Catégories et versionnage du consentement
│   ├── Services/         # Registre de services tiers (un fichier = un service)
│   ├── Database/         # Accès aux réglages (wp_options)
│   └── Frontend/         # Assets et rendu du runtime public
├── src/
│   ├── admin/            # Application React (wizard, dashboard, ...)
│   └── frontend/         # Runtime public (JS natif)
├── data/
│   └── cookies.json      # Base locale de cookies (Open Cookie Database, Apache-2.0)
├── build/                # Généré par `npm run build`
├── languages/            # Fichiers de traduction
├── tests/                # Tests PHP et JS
├── bin/release.mjs       # Script de release
└── AGENTS.md / PROJECT.md / ARCHITECTURE.md / STATUS.md / RELEASE.md / CHANGELOG.md
```

Détail complet dans [ARCHITECTURE.md](ARCHITECTURE.md).

## Tests

- **JS** : `npm test` (basé sur `@wordpress/scripts test-unit-js`). Aucun test
  n'est encore écrit — voir [STATUS.md](STATUS.md) pour les prochaines
  étapes.
- **PHP** : dossier `tests/php/` réservé pour une suite PHPUnit à venir.

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
