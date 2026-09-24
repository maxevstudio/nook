# tests/js/

Tests JS à écrire (voir STATUS.md).

`@wordpress/scripts test-unit-js` (version installée dans ce dépôt) est basé
sur **Vitest 5 + Vite 8**, non inclus par défaut : les installer avec
`npm install --save-dev vitest@^5 vite@^8` avant de pouvoir exécuter
`npm test`.

**Note (2026-09-24)** : une tentative d'installation dans cet environnement
de développement a échoué à deux reprises avec l'erreur npm
`Cannot read properties of null (reading 'edgesOut')` (bug connu de
résolution de peer dependencies dans `@npmcli/arborist`, npm 10.9.2).
Contourner avec `--legacy-peer-deps` permet l'installation mais casse
ensuite la résolution d'`eslint-plugin-jsdoc` (`npm run lint:js` échoue avec
`Cannot find package 'eslint'`), rendant le dépôt instable. La tentative a
été annulée (`git checkout -- package.json package-lock.json` puis
`npm ci`) pour ne pas casser le pipeline vérifié. À réessayer dans un
environnement avec une version de npm plus récente, ou en épinglant des
versions exactes de `vitest`/`vite` plutôt que des plages `^`.
