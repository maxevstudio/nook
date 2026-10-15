# RELEASE.md — Workflow de release

## Versionnage

Nook suit le [Semantic Versioning](https://semver.org/) : `MAJOR.MINOR.PATCH`.

- **MAJOR** : rupture de compatibilité (format de stockage, structure de
  données publique, retrait de fonctionnalité).
- **MINOR** : nouvelle fonctionnalité rétrocompatible.
- **PATCH** : correctif, sans nouvelle fonctionnalité.

Pendant la phase de fondations, la version de travail est `0.1.0-dev` (voir
[STATUS.md](STATUS.md)). Aucune release publique n'est encore taguée.

## Publier une version (procédure complète)

Les sites clients se mettent à jour automatiquement depuis les releases
GitHub (voir ARCHITECTURE.md §15). Pour publier `X.Y.Z` :

```bash
npm run release -- --version=X.Y.Z   # applique la version + CHANGELOG, ZIP local
git add -A && git commit -m "release: X.Y.Z"
git tag vX.Y.Z
git push origin main --tags
```

Le push du tag déclenche `.github/workflows/release.yml`, qui relance les
tests, reconstruit le ZIP avec `npm run release -- --package-only` (aucun
fichier modifié, refus si le tag ne correspond pas à `package.json`/
`NOOK_VERSION`) et l'attache à la release GitHub. Les sites voient la mise à
jour sous 6 h maximum (cache de l'updater), ou immédiatement via
« Tableau de bord → Mises à jour → Vérifier à nouveau » après expiration du
cache.

## Commandes

```bash
npm run release
npm run release -- --version=1.2.0
npm run release -- --package-only   # CI : empaquette la version commitée
```

Sans `--version`, le script propose un incrément `patch` par défaut à partir
de la version actuelle de `package.json`/`nook.php`.

## Étapes exécutées par `npm run release` (`bin/release.mjs`)

1. **Vérifier le projet** — répertoire de travail propre attendu pour les
   fichiers suivis (avertissement si `git` indisponible/absent, la release
   continue quand même mais le signale).
2. **Lint / tests** — `npm run lint:js`, `npm run lint:css` si configurés,
   `npm run test:php`, `npm test` si des tests JS existent. Un échec bloquant arrête la release.
3. **Build de production** — `npm run build` (via `wp-scripts build`),
   génère `build/admin/` et `build/frontend/`.
4. **Appliquer la version** — calcule la version finale (argument
   `--version` ou incrément semver) et l'écrit dans `package.json`.
5. **Mettre à jour l'en-tête du plugin** — `Version:` dans `nook.php`.
6. **Mettre à jour les constantes de version** — `NOOK_VERSION` dans
   `nook.php` et `Stable tag:` dans `readme.txt`.
7. **Mettre à jour `CHANGELOG.md`** — transforme la section `[Unreleased]` en
   section versionnée datée (voir format ci-dessous). Refuse de continuer si
   `[Unreleased]` est vide (aucun changement réel à publier).
8. **Ajouter la date** de release (fuseau du système, format ISO côté
   `build-manifest.json`, format lisible côté `CHANGELOG.md`).
9. **Préparer le dossier de distribution** — `dist/nook/` recréé à vide.
10. **Copier uniquement les fichiers utiles** dans `dist/nook/` (voir liste
    d'inclusion/exclusion ci-dessous).
11. **Vérifier la distribution** — présence de `nook.php`, absence de
    `node_modules/`, `.git/`, `src/`, taille raisonnable, `build-manifest.json`
    généré.
12. **Générer le ZIP** — `dist/nook-<version>.zip`, dossier racine `nook/`.

Si une étape critique échoue (lint, tests, build, vérification de la
distribution), **aucune release n'est produite** : le script s'arrête avec un
code de sortie non nul et un message explicite.

## Contenu du ZIP

```
dist/
├── nook/
│   ├── nook.php
│   ├── uninstall.php
│   ├── includes/
│   ├── build/
│   ├── data/
│   ├── assets/
│   ├── languages/
│   ├── readme.txt
│   ├── build-manifest.json
│   └── LICENSE
│
└── nook-1.0.0.zip
```

Le ZIP contient `nook/` comme dossier racine et est installable directement
depuis l'écran WordPress **Extensions → Ajouter → Téléverser**.

## Exclusions systématiques du ZIP

```
node_modules/
.git/
.github/
.vscode/
.idea/
tests/
docs/
src/
dist/
*.map
package.json / package-lock.json (non nécessaires à l'exécution)
bin/
fichiers temporaires, configurations IDE, scripts de développement
```

## `build-manifest.json`

Généré à chaque release à la racine de `dist/nook/` :

```json
{
  "name": "Nook",
  "version": "1.0.0",
  "buildDate": "2026-09-24",
  "environment": "production"
}
```

## Format du CHANGELOG

Voir [CHANGELOG.md](CHANGELOG.md). Format « Keep a Changelog » simplifié :

```
## 1.1.0 — 24 septembre 2026

### Added
- ...

### Changed
- ...

### Fixed
- ...
```

## État actuel du script de release

`bin/release.mjs` existe et implémente les étapes 3 à 12 (build, version,
changelog, packaging, ZIP). L'étape 2 (lint/tests) s'exécute si les scripts
correspondants existent dans `package.json` ; elle est actuellement
best-effort tant que la suite de tests n'est pas écrite (voir
[STATUS.md](STATUS.md)).
