# AGENTS.md — Règles non négociables du projet Nook

Ce fichier s'adresse à **tout agent (humain ou IA)** qui travaille sur ce dépôt.

Le dépôt est la seule source de vérité du projet. **Une conversation n'est jamais
la source de vérité.** Aucune session future ne doit supposer qu'elle a accès à
l'historique d'une conversation précédente.

## 0. Avant toute modification importante

1. Lire `AGENTS.md` (ce fichier).
2. Lire [PROJECT.md](PROJECT.md).
3. Lire [ARCHITECTURE.md](ARCHITECTURE.md).
4. Lire [STATUS.md](STATUS.md).
5. Lire [RELEASE.md](RELEASE.md) si le travail concerne le build ou une release.
6. Inspecter le code existant concerné avant de le modifier.
7. Seulement ensuite, commencer les changements.

Ne jamais dépendre uniquement du contexte d'une conversation précédente : si une
information importante n'est pas dans le dépôt, elle est considérée comme perdue.

## 1. Règles de méthode

- Ne jamais recréer inutilement une fonctionnalité qui existe déjà : chercher
  d'abord dans `includes/`, `src/` avant d'écrire du nouveau code.
- Maintenir la documentation (`ARCHITECTURE.md`, `PROJECT.md`) à jour dès qu'une
  décision structurelle change.
- Maintenir `STATUS.md` à jour après chaque étape importante (pas après chaque
  micro-commit). `STATUS.md` décrit un état, pas un journal de conversation.
- Respecter le workflow décrit dans `RELEASE.md` pour toute publication.
- Ne jamais modifier une décision structurelle importante (architecture,
  stockage du consentement, choix technologique) sans justifier le changement
  dans la documentation correspondante.

## 2. Règles fondamentales du produit et de la technique

1. **Administration React obligatoire.** L'admin de Nook est une application
   React, jamais une succession de pages de réglages PHP classiques.
2. **Composants natifs WordPress en priorité.** Utiliser `@wordpress/components`
   avant d'écrire un composant maison.
3. **Packages `@wordpress/*` en priorité** pour tout ce que l'écosystème
   WordPress fournit déjà (data, i18n, api-fetch, icons, element, scripts...).
4. **Ne jamais embarquer une copie inutile de React ou ReactDOM.** On utilise le
   `react`/`react-dom` fournis par WordPress via l'externalisation de
   `@wordpress/scripts` (`wp-scripts build`) et les `*.asset.php` générés.
5. **PHP = backend WordPress.** Bootstrap, REST API, permissions, stockage,
   scanner serveur, hooks, front-end minimal.
6. **Front-end public extrêmement léger.** Pas de React côté visiteur. JS natif
   moderne, CSS minimal.
7. **Aucun compte externe obligatoire** pour utiliser le plugin.
8. **Aucun email obligatoire.**
9. **Aucune télémétrie par défaut.**
10. **Aucun SaaS obligatoire** pour les fonctionnalités principales.
11. **Aucun upsell ni publicité** dans l'administration.
12. **Le refus des cookies doit être aussi accessible que l'acceptation** —
    aucun dark pattern.
13. **Les scripts soumis au consentement doivent être réellement bloqués**
    avant obtention du consentement (pas seulement visuellement masqués).
14. **Architecture modulaire** : un service, un scanner, un module de stockage
    ne doivent pas être mélangés dans une classe monolithique.
15. **Respect des standards WordPress actuels** (Coding Standards, REST API,
    i18n, sécurité, accessibilité).
16. **Toute release doit générer un ZIP WordPress propre**, installable
    directement, via `npm run release`.
17. **Le ZIP de production ne doit jamais contenir l'intégralité du dépôt de
    développement** (pas de `node_modules/`, `.git/`, `src/`, `tests/`, etc.).

## 3. Hors scope (rappel)

Ne pas prioriser dans les premières versions : A/B testing, analytics
marketing, cloud Maxev, SaaS, marketplace, compte utilisateur Nook, licence
commerciale, IA, tous les pays du monde, TCF publicitaire complexe, reporting
avancé. Voir [PROJECT.md](PROJECT.md) pour le détail.

## 4. En cas de doute

Préférer la simplicité, la sobriété et la conformité WordPress native à
l'ajout de fonctionnalités. Nook doit rester installable et utilisable sans
compte, sans email et sans SaaS obligatoire.
