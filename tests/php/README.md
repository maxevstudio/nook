# tests/php/

Suite de tests PHP avec un framework maison sans dépendance (pas de PHPUnit
ni de Composer requis à l'exécution — cohérent avec AGENTS.md : le plugin
doit rester installable par simple copie de dossier).

## Exécuter les tests

```bash
php tests/php/run.php
```

## Structure

- `bootstrap.php` — stubs des fonctions/classes WordPress réellement
  utilisées par le code de Nook (options en mémoire, filtres, HTTP simulé,
  etc.), autoloader PSR-4 identique à celui de `nook.php`.
- `framework.php` — `nook_test()`, `nook_assert()`, `nook_assert_equals()`,
  `nook_run_tests()`.
- `run.php` — point d'entrée : charge le bootstrap, le framework, tous les
  fichiers `*Test.php`, exécute les tests et retourne un code de sortie non
  nul en cas d'échec (utilisable en CI).
- Un fichier `*Test.php` par module testé : `SettingsTest`, `MigrationsTest`,
  `CategoryRegistryTest`, `CookieDatabaseTest`, `ServiceRegistryTest`,
  `ScannerTest`, `BlocklistTest`, `ContentFilterTest` (blocage réel de
  scripts/iframes — la fonctionnalité la plus critique du projet),
  `SettingsControllerTest`, `ScanControllerTest`, `ServicesControllerTest`,
  `CookiesControllerTest`, `PluginBootTest`.

## Ajouter un test

Créer un fichier `MonModuleTest.php` dans ce dossier, avec un ou plusieurs
appels `nook_test( 'description', function () { ... } );`. Il sera chargé
automatiquement par `run.php`.
