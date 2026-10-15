# ARCHITECTURE.md — Architecture technique de Nook

Ce document décrit l'architecture réelle (ou visée) du plugin. Toute décision
structurelle importante doit être reflétée ici. Si le code diverge de ce
document, corriger l'un ou l'autre au moment où la divergence est constatée.

## 1. Vue d'ensemble

```
PHP (WordPress backend)          React (administration)        JS natif (front public)
────────────────────────         ───────────────────────       ───────────────────────
bootstrap du plugin               wizard d'installation          bannière de consentement
REST API                          dashboard                      centre de préférences
permissions / sécurité            éditeur de cookies/services     script blocking runtime
stockage (options + CPT léger)    éditeur de bannière (design)    placeholders iframes tiers
scanner serveur                   éditeur de politique
script blocking (rendu HTML)
hooks WordPress (shortcodes...)
```

Principe directeur : **PHP décide de ce qui est bloqué/autorisé et sert les
données** ; **React ne fait que piloter l'administration** ; **le runtime
public ne dépend jamais de React**.

## 2. Arborescence du dépôt

```
nook/
│
├── nook.php                  # Bootstrap du plugin (en-tête WP, constantes, init)
├── uninstall.php             # Nettoyage des données à la désinstallation
├── LICENSE
│
├── includes/                 # PHP — namespace Nook\
│   ├── Plugin.php            # Orchestrateur principal (singleton léger)
│   ├── Admin/                # Menu admin, enqueue des assets React
│   ├── REST/                 # Contrôleurs REST (un fichier = une ressource) :
│   │   ├── SettingsController.php
│   │   ├── ScanController.php
│   │   ├── ServicesController.php
│   │   ├── CookiesController.php
│   │   └── PublicConfigController.php
│   ├── Scanner/               # Scanner serveur (Scanner.php, UrlDiscovery.php)
│   ├── Consent/               # CategoryRegistry (catégories de consentement)
│   ├── Services/              # ServiceDefinition, ServiceRegistry, ServiceState,
│   │                          # un fichier par service (GoogleAnalytics.php, ...)
│   ├── Database/              # Settings, Migrations, ScanResults, CookieDatabase
│   ├── Frontend/              # Assets, ContentFilter, Blocklist, Placeholder,
│   │                          # Shortcodes (blocage réel + rendu public)
│   └── Updater/               # GitHubUpdater (mises à jour via releases GitHub, §15)
│
├── src/                       # Sources JS/JSX (non distribuées, compilées en build/)
│   ├── admin/                 # Application React de l'administration
│   │   ├── index.js
│   │   ├── App.jsx
│   │   ├── store/              # @wordpress/data (store "nook")
│   │   ├── components/         # ColorField, ...
│   │   └── pages/
│   │       ├── Onboarding.jsx
│   │       ├── Wizard.jsx       # Orchestrateur du wizard 7 étapes
│   │       ├── wizard/          # Un composant par étape (SiteStep, ScanStep, ...)
│   │       ├── Dashboard.jsx    # Conteneur à onglets (TabPanel)
│   │       └── dashboard/       # Overview, CookiesPage, ServicesPage,
│   │                            # BannerPage, PolicyPage, SettingsPage
│   │
│   └── frontend/              # Runtime public (vanilla JS, sans React)
│       ├── index.js            # Orchestrateur (bannière/préférences/blocage)
│       ├── consent-storage.js  # Lecture/écriture du consentement navigateur
│       ├── blocker.js          # Réactivation des scripts/iframes bloqués
│       ├── banner.js           # Bannière de consentement
│       ├── preferences.js      # Centre de préférences (modal, focus trap)
│       ├── events.js           # Petite API d'événements nook:*
│       └── dom.js              # Utilitaire de création d'éléments DOM
│
├── data/
│   └── cookies.json           # Base de connaissance locale des cookies/services
│
├── assets/                    # Images, icônes statiques non générées par build
├── build/                     # Sortie de @wordpress/scripts (généré, non versionné dans git mais livré en release)
├── languages/                 # Fichiers .pot/.po/.mo
├── tests/
│   ├── php/                   # Tests PHP (framework maison, voir §13) : `php tests/php/run.php`
│   └── js/                    # Tests JS (à définir : @wordpress/scripts test-unit-js)
├── docs/                      # Notes techniques longues, ADR ponctuels
├── bin/                       # Scripts de release / build (Node)
│
├── AGENTS.md
├── PROJECT.md
├── ARCHITECTURE.md
├── STATUS.md
├── RELEASE.md
├── CHANGELOG.md
├── README.md
├── readme.txt                 # Format WordPress.org (banner/FAQ/changelog court)
├── package.json
└── package-lock.json
```

`build/` est généré par `npm run build` et n'est pas versionné en développement
courant (`.gitignore`), mais **doit** être présent dans le ZIP de release (voir
[RELEASE.md](RELEASE.md)).

## 3. PHP — organisation et conventions

- Namespace racine : `Nook\`. PSR-4 : `Nook\Admin\`, `Nook\REST\`,
  `Nook\Scanner\`, `Nook\Consent\`, `Nook\Services\`, `Nook\Database\`,
  `Nook\Frontend\` mappent respectivement vers `includes/Admin/`,
  `includes/REST/`, etc.
- Autoloading : un autoloader PSR-4 minimal maison dans `nook.php` (pas de
  dépendance Composer obligatoire à l'exécution, pour rester installable par
  simple copie de dossier — cf. règle « aucune dépendance cloud/obligatoire »).
  Composer peut être utilisé en développement (tests, outils qualité) via
  `require-dev` uniquement.
- `Plugin.php` est le seul point d'orchestration : il instancie les modules et
  accroche les hooks WordPress (`plugins_loaded`, `init`, `rest_api_init`,
  `admin_menu`, ...). Les modules ne s'auto-bootstrapent pas seuls.
- Chaque service tiers (Google Analytics, GTM, Meta Pixel, YouTube, ...) est
  une classe dans `includes/Services/` implémentant une interface commune
  `ServiceDefinition`. **Pas de classe monolithique** listant tous les
  services.
- Prefix des fonctions procédurales éventuelles : `nook_`. Prefix des hooks
  custom : `nook/...` (hooks WP modernes) ou `nook_...` selon convention
  historique WordPress.

### 3.1 Modèle de consentement (`includes/Consent/`)

- `Category.php` : définition d'une catégorie (slug, libellé, description,
  `is_required` bool).
- `CategoryRegistry.php` : les 4 catégories par défaut (necessary, functional,
  statistics, marketing), extensible via filtre `nook/consent/categories`.
- `ConsentRecord.php` : structure de valeur représentant un enregistrement de
  consentement (voir format côté navigateur, section 6).
- `ConsentVersion.php` : gestion de `consent_configuration_version` (voir
  section 7).

### 3.2 Registre de services (`includes/Services/`)

Interface minimale (nom indicatif, à affiner en implémentation) :

```php
interface ServiceDefinition {
    public function get_slug(): string;
    public function get_name(): string;
    public function get_category(): string;      // necessary|functional|statistics|marketing
    public function get_domains(): array;         // ex: ['google-analytics.com']
    public function get_cookie_patterns(): array;  // ex: ['_ga', '_ga_*']
    public function get_script_patterns(): array;  // signatures JS/URL à intercepter
    public function get_iframe_patterns(): array;  // domaines à transformer en placeholder
    public function get_description(): string;
    public function get_privacy_url(): ?string;
}
```

`ServiceRegistry.php` charge les services natifs (V1 : Google Analytics/GA4,
Google Tag Manager, Google Ads, Meta Pixel, YouTube, Vimeo, Google Maps) et
expose un filtre `nook/services/register` pour en ajouter d'autres sans
toucher au cœur.

### 3.3 Scanner (`includes/Scanner/`)

Le scanner **serveur** est implémenté (`Scanner::scan()`, `UrlDiscovery::discover()`) :

1. `UrlDiscovery::discover()` sélectionne une liste d'URLs internes limitée
   (page d'accueil, page de contact si identifiable par slug/titre, jusqu'à 5
   pages et 5 articles récents publiés, pages boutique/panier WooCommerce si
   WooCommerce est actif), plafonnée à `UrlDiscovery::DEFAULT_LIMIT` (15).
2. `Scanner::scan()` traite au plus `Scanner::MAX_URLS` (20) URLs, avec un
   timeout de `Scanner::REQUEST_TIMEOUT` (8s) par requête (`wp_remote_get`).
   Pour chaque page récupérée avec succès :
   - extraction des balises `<script>` (externes et inline) et `<iframe>` via
     `DOMDocument` (repli sur une extraction par expression régulière si
     `DOMDocument` est indisponible) ;
   - les cookies **HTTP** effectivement renvoyés par le serveur pour cette
     page (`wp_remote_retrieve_cookies()`) sont capturés ;
   - confrontation aux définitions de `ServiceRegistry` (domaines et motifs
     de script/iframe) et à quelques indices de plugins actifs connus (ex.
     Google Site Kit → Google Analytics/GTM potentiels, via
     `get_option('active_plugins')`). Les motifs d'iframe sont aussi
     cherchés dans les scripts inline : une iframe créée en JavaScript (ex.
     lecteur YouTube chargé au clic) n'apparaît que là ;
   - repérage de Google Fonts (`fonts.googleapis.com/css`,
     `fonts.gstatic.com/s/`) : ce n'est pas un cookie et la bannière ne peut
     pas le régler, mais l'IP du visiteur part chez Google (jugé contraire
     au RGPD, LG München 2022). Remonté comme **avertissement**
     (`scanner.warnings`, code `google-fonts`), jamais comme service ;
     affiché par `src/admin/components/ScanWarnings.jsx`.
3. Les cookies **JavaScript** exécutés dynamiquement et
   `localStorage`/`sessionStorage` ne sont **pas** observables depuis une
   requête HTTP côté serveur : ce n'est donc jamais prétendu (voir
   PROJECT.md §16). Seuls les cookies HTTP réellement renvoyés sont détectés
   par le scan serveur.
4. Chaque cookie observé est confronté à `Nook\Database\CookieDatabase`
   (Open Cookie Database, voir section 5) selon l'algorithme décrit
   ci-dessous, avec trois statuts possibles :
   - **`recognized`** — correspondance exacte ou motif glob explicite.
   - **`suggested`** — correspondance par préfixe dynamique (nom de cookie
     variable, ex. `_ga_ABCD123`), seulement si le préfixe fait au moins
     `CookieDatabase::MIN_PREFIX_LENGTH` (4) caractères (évite les faux
     positifs d'un préfixe trop court comme `_s`).
   - **`to_review`** — aucune correspondance suffisamment fiable,
     l'administrateur catégorise manuellement.
5. Un verrou (`get_transient`/`set_transient`, clé `nook_scan_lock`, TTL 180s)
   empêche deux analyses simultanées (voir `Nook\REST\ScanController`).
   L'analyse est **synchrone** (le temps de requête REST couvre tout le
   scan) : volontairement simple pour la V1, sans file d'attente
   asynchrone ni tâche cron, car le nombre d'URLs et le timeout par requête
   restent bornés.
6. Le scanner ne dépend d'aucune infrastructure cloud Maxev : tout tourne sur
   l'installation WordPress elle-même. Les résultats bruts sont stockés dans
   une option dédiée `nook_scan_results` (`Nook\Database\ScanResults`),
   séparée de `nook_settings` pour ne pas alourdir cette dernière.
7. Un scan ultérieur **ne réinitialise jamais** les corrections manuelles :
   `Nook\REST\ServicesController`/`CookiesController` fusionnent toujours
   les résultats de détection avec les corrections stockées dans
   `nook_settings.services`/`nook_settings.cookies` (voir
   `Nook\Services\ServiceState` pour le calcul d'état partagé).

### 3.4 Script blocking (`includes/Frontend/`)

Le blocage est **réel**, implémenté par `Nook\Frontend\ContentFilter` :

- `ContentFilter::maybe_start_buffer()` (hook `template_redirect`, priorité
  0) calcule `Nook\Frontend\Blocklist::build()` — la liste des services
  actuellement gérés par Nook et soumis à consentement (catégorie autre que
  « necessary », `enabled` vrai selon `ServiceState`). Si la liste est vide,
  ou si la requête est une page d'administration/AJAX/REST/cron/flux, aucune
  bufferisation n'est démarrée (coût nul sur le reste du site).
- Sinon, `ob_start()` capture la sortie HTML complète de la page et
  `ContentFilter::filter_output()` la réécrit par **expressions régulières
  ciblées** (jamais par un aller-retour `DOMDocument` → `saveHTML()`, qui
  risquerait de corrompre la page entière — encodage, balises
  auto-fermantes, etc.). Seules les balises `<script>`/`<iframe>`
  correspondant à un service bloqué sont réécrites ; tout le reste du
  document est laissé strictement intact.
- Un `<script src="...">` correspondant devient
  `<script type="text/plain" data-nook-category="…" data-nook-service="…" data-nook-src="…">`
  (le navigateur ne l'exécute plus, l'attribut `src` étant renommé). Un
  script inline dont le contenu correspond à une signature connue (ex.
  `gtag(`, `fbq(`) est neutralisé de la même façon sans que son contenu ne
  soit modifié.
- La correspondance vérifie d'abord les motifs **spécifiques** au service
  (`scriptPatterns`/`iframePatterns`, ex. `gtm.js` vs `gtag/js`) avant de se
  rabattre sur les domaines génériques : cela désambiguïse les services qui
  partagent un domaine (ex. `googletagmanager.com` utilisé à la fois par
  Google Analytics et Google Tag Manager).
- Scripts inline : ne sont jamais touchés les scripts non exécutables
  (`application/ld+json`, templates… — casser le JSON-LD nuirait au SEO),
  ceux de Nook (`id="nook-*"`), ni un script qui ne fait que **construire
  une iframe d'embed** (motif `iframePatterns` sans motif `scriptPatterns`) :
  c'est alors l'iframe elle-même que bloque le garde navigateur (ci-dessous),
  ce qui préserve le reste du script. Un chargeur inline reconnu par son
  seul domaine (ex. service personnalisé Hotjar) reste neutralisé.
- Une `<iframe src="...">` correspondante est remplacée par un placeholder
  (`Nook\Frontend\Placeholder::render()`) conservant l'URL d'origine,
  la largeur/hauteur/titre, et un bouton « Autoriser et afficher ».
- Le runtime front (`src/frontend/blocker.js`) ne réactive un script/iframe
  bloqué qu'après consentement explicite de la catégorie correspondante (ou
  clic individuel sur le placeholder), en reconstruisant un vrai élément
  DOM (jamais en modifiant simplement `type=`/l'affichage CSS).
- Le mécanisme fonctionne même si le visiteur a JavaScript désactivé : dans
  ce cas, les scripts/iframes neutralisés restent simplement non exécutés
  (fail-safe = bloqué).
- **Blocage dynamique des iframes** (`src/frontend/guard.js`, entrée
  webpack `frontend/guard`, ~3 Ko) : chargé seul dans le `<head>` avant tout
  autre script (`Assets::enqueue_guard()`, uniquement si un service est
  bloqué), avec une config inline minimale (`window.nookGuardConfig` :
  signatures d'iframes des services bloqués, textes du placeholder). Il
  intercepte `iframe.src = …` et `setAttribute( 'src', … )` : une URL
  bloquée est mise de côté (`data-nook-src`) **avant toute requête**, puis
  l'iframe est remplacée à l'insertion par le même placeholder que côté
  serveur (MutationObserver ; rattrape aussi les iframes insérées via
  `innerHTML`, en dernier recours). Le placeholder conserve `allow`, `class`
  et `style` pour une réactivation fidèle, et reprend la position de
  l'iframe si le thème la place en absolu (lecteur posé sur une vignette).
  `blocker.js` lève le blocage via `window.nookGuard.allow()` dès que la
  catégorie est acceptée. Validé en conditions réelles (lecteur YouTube
  `youtube-nocookie` chargé au clic, aucune requête avant consentement).
- Limite assumée (voir PROJECT.md §47) : un **script** injecté
  dynamiquement côté client (ex. après un appel AJAX) peut encore échapper
  au filtre serveur — le garde ne couvre que les iframes. Aucun outil ne
  peut garantir un blocage à 100 % sans exécuter un vrai navigateur.

### 3.5 Stockage (`includes/Database/`)

- Réglages plugin (site, consentement, bannière, politique, scanner,
  services, cookies, wizard) : `wp_options`, clé `nook_settings`
  (`Nook\Database\Settings`), schéma décrit en détail section 3.6.
- Résultats bruts du dernier scan (cookies/services/scripts/iframes/erreurs
  détectés) : option séparée `nook_scan_results`
  (`Nook\Database\ScanResults`), pour ne jamais alourdir `nook_settings`.
- Le consentement des visiteurs **n'est pas stocké côté serveur par défaut**
  (respect du principe « aucune donnée envoyée pour le fonctionnement normal
  ») : il vit côté navigateur (voir section 6). Une preuve de consentement
  serveur pourra être une extension future opt-in, hors scope V1.
- Migrations légères gérées par `Nook\Database\Migrations::apply()` : un
  numéro de schéma (`schemaVersion`, comparé à `NOOK_DB_VERSION`) déclenche
  séquentiellement les transformations nécessaires (voir section 3.7).

### 3.6 Modèle de configuration (`nook_settings`)

Schéma complet produit par `Settings::get_defaults()` et retourné par
`GET /nook/v1/settings` :

```json
{
  "schemaVersion": 1,
  "setupCompleted": false,
  "site": { "country": "FR", "language": "fr", "type": "business" },
  "consent": {
    "configVersion": 1,
    "duration": 180,
    "categories": { "necessary": { "...": "..." }, "functional": {}, "statistics": {}, "marketing": {} }
  },
  "banner": {
    "title": "Votre vie privée",
    "text": "…",
    "buttons": { "acceptAll": "…", "rejectAll": "…", "customize": "…" },
    "position": "bottom",
    "backgroundColor": "#ffffff",
    "textColor": "#1e1e1e",
    "accentColor": "#3858e9",
    "buttonStyle": "solid",
    "radius": 4,
    "width": "full",
    "overlay": false,
    "footerLink": true
  },
  "policy": { "mode": "create", "pageId": null, "title": "Politique de cookies" },
  "scanner": { "status": "idle", "lastScanAt": null, "urls": [], "summary": { "cookies": 0, "services": 0, "toReview": 0 } },
  "services": {},
  "cookies": {},
  "wizard": { "currentStep": "site", "completedSteps": [] },
  "debug": { "enabled": false }
}
```

- `site.type` : `business` (site vitrine) | `ecommerce` | `other`.
- `consent.categories` : copie des catégories de `CategoryRegistry`,
  personnalisable (descriptions) depuis l'étape « Consentement » du wizard.
- `services`/`cookies` : uniquement des **corrections manuelles** (activation,
  recatégorisation, confirmation, ajout personnalisé) indexées par slug/id —
  jamais les résultats de détection eux-mêmes, qui vivent dans
  `nook_scan_results` (voir section 3.5) et sont fusionnés à la volée par
  `Nook\REST\ServicesController`/`CookiesController`.
- `Settings::merge()` fusionne récursivement deux tableaux, avec une règle
  importante : une **liste séquentielle** (ex. `wizard.completedSteps`,
  `scanner.urls`) est **remplacée intégralement**, jamais fusionnée index
  par index (sinon une liste plus courte ne pourrait jamais réellement
  raccourcir une valeur déjà stockée) ; les tableaux associatifs continuent
  d'être fusionnés clé par clé.
- `Nook\REST\SettingsController` nettoie les données reçues
  (`sanitize_key`-like mais **sans forcer la casse en minuscules**, pour ne
  pas corrompre les clés camelCase du schéma) et rejette silencieusement
  toute valeur hors énumération pour les champs sensibles (`site.type`,
  `banner.position`, `policy.mode`, ...).

### 3.7 Migrations de schéma (`Nook\Database\Migrations`)

- Chaque évolution du schéma incrémente `NOOK_DB_VERSION` et ajoute une
  entrée dans `Migrations::steps()`, indexée par la version de schéma
  cible qu'elle produit.
- `Migrations::apply()` applique séquentiellement toutes les migrations dont
  la version cible est supérieure à `schemaVersion` stocké, jusqu'à
  atteindre `NOOK_DB_VERSION`.
- V1 ne contient qu'une migration « identité » (version 1, schéma initial) :
  le mécanisme existe et est testé (`tests/php/MigrationsTest.php`), prêt à
  accueillir une vraie transformation dès qu'une V2 en aura besoin, sans
  qu'aucune session future n'ait à réinventer l'architecture.

## 4. REST API (`includes/REST/`)

Namespace : `nook/v1`. Tous les contrôleurs suivants sont implémentés :

| Méthode    | Route                     | Contrôleur               | Rôle                                                          | Capability |
|------------|---------------------------|---------------------------|----------------------------------------------------------------|------------|
| GET        | `/nook/v1/settings`       | `SettingsController`      | Lire la configuration courante                                  | `manage_options` |
| POST       | `/nook/v1/settings`       | `SettingsController`      | Sauvegarder un fragment de configuration (fusion profonde)      | `manage_options` |
| POST       | `/nook/v1/settings/reset` | `SettingsController`      | Réinitialiser entièrement la configuration                      | `manage_options` |
| POST       | `/nook/v1/scan`           | `ScanController`          | Démarrer une analyse (synchrone, verrouillée)                   | `manage_options` |
| GET        | `/nook/v1/scan/status`    | `ScanController`          | Dernier statut/résultats connus, sans relancer d'analyse        | `manage_options` |
| GET        | `/nook/v1/services`       | `ServicesController`      | Lister les services (registre + détection + corrections)        | `manage_options` |
| POST       | `/nook/v1/services/:slug` | `ServicesController`      | Confirmer/corriger un service, ou créer un service personnalisé | `manage_options` |
| GET        | `/nook/v1/cookies`        | `CookiesController`       | Lister les cookies (détection + corrections)                    | `manage_options` |
| POST/PUT   | `/nook/v1/cookies/:id`    | `CookiesController`       | Corriger un cookie, ou ajouter une entrée manuelle              | `manage_options` |
| GET        | `/nook/v1/public-config`  | `PublicConfigController`  | Config publique servie au runtime front                         | public (lecture seule) |

Toute route en écriture applique : `permission_callback`
(`current_user_can( 'manage_options' )`), nonce REST WordPress standard
(`X-WP-Nonce`, géré nativement par `@wordpress/api-fetch`), sanitation et
validation des valeurs sensibles (énumérations) côté serveur. Aucune donnée
du navigateur n'est jamais utilisée sans validation (voir
`tests/php/SettingsControllerTest.php` pour le test de régression du bug
historique de casse des clés).

## 4.1 Comment ajouter un nouveau service à Nook

1. Créer `includes/Services/MonService.php` implémentant
   `Nook\Services\ServiceDefinition` (voir `GoogleAnalytics.php` comme
   modèle).
2. L'enregistrer dans `Nook\Services\ServiceRegistry::get_services()` (ou,
   pour un plugin tiers, via le filtre `nook/services/register` — pas besoin
   de modifier le cœur de Nook).
3. Documenter ses domaines, motifs de script/iframe et cookies connus avec
   soin : des motifs trop larges provoquent des faux positifs (voir
   PROJECT.md §25).
4. Ajouter un test dans `tests/php/ServiceRegistryTest.php` (au minimum :
   slug unique, catégorie par défaut cohérente).
5. Si le service doit être détecté par un indice de plugin actif (comme
   Google Site Kit pour GA/GTM), ajouter l'entrée dans
   `Scanner::get_active_plugin_hints()`.
6. Aucune modification du blocage front n'est nécessaire : `Blocklist`,
   `ContentFilter` et le runtime front travaillent uniquement à partir des
   catégories/motifs déclarés par la définition du service.

## 5. Base de connaissance des cookies (`data/cookies.json`)

**Source** : [Open Cookie Database](https://github.com/jkwakman/Open-Cookie-Database)
par jkwakman, sous licence **Apache License 2.0** (vérifiée directement sur le
dépôt source à l'intégration). Cette licence permissive autorise la
redistribution et la modification, à condition de conserver la mention de
licence et d'indiquer les modifications apportées — c'est ce que fait ce
fichier.

- Fichier de licence conservé dans `data/OPEN-COOKIE-DATABASE-LICENSE` (copie
  de la licence Apache-2.0 du projet source).
- `data/cookies.json` est une **transformation normalisée** (pas une copie
  brute) du JSON source : les catégories d'origine (`Functional`,
  `Personalization`, `Analytics`, `Marketing`, `Security`) sont mappées vers
  les 4 catégories Nook :
  - `Analytics` → `statistics`
  - `Marketing` → `marketing`
  - `Personalization` → `functional`
  - `Functional` → `functional` (sauf cookies clairement strictement
    nécessaires, laissés en `necessary` au cas par cas)
  - `Security` → `necessary`
- Schéma normalisé par entrée :

```json
{
  "id": "ga4-_ga",
  "service": "Google Analytics",
  "cookie": "_ga",
  "wildcard": false,
  "category": "statistics",
  "domain": "google-analytics.com",
  "description": "Utilisé pour distinguer les utilisateurs.",
  "retention": "2 years",
  "dataController": "Google",
  "privacyUrl": "https://policies.google.com/privacy",
  "source": "open-cookie-database"
}
```

- Le scanner ne dépend d'**aucune API distante** pour la reconnaissance
  standard : `data/cookies.json` est chargé localement à chaque analyse, une
  seule fois par requête PHP (`Nook\Database\CookieDatabase`, chargement
  paresseux mis en cache statique).
- Algorithme de correspondance (`CookieDatabase::match()`), par ordre de
  priorité : (1) correspondance exacte insensible à la casse ; (2) motif
  glob explicite (rare, présence d'un `*` littéral dans le nom, ex.
  `DSP2F_*`) ; (3) préfixe (`wildcard: true` dans la base, ex. `_ga_`
  matchant `_ga_ABCD123`), uniquement si le préfixe fait au moins 4
  caractères — en dessous, le risque de faux positif (préfixes comme `_s`,
  `CT`) est jugé trop élevé et la correspondance est ignorée.
- **CookieSearch.org** est une ressource documentaire humaine uniquement :
  aucun scraping automatique, aucune API privée, aucune dépendance technique
  du plugin envers ce site. Une intégration future nécessiterait de vérifier
  au préalable une licence et une API officielle publiées par CookieSearch.

## 6. Stockage du consentement côté navigateur

Implémenté dans `src/frontend/consent-storage.js`. Consentement mémorisé
dans un cookie first-party (`nook_consent`, source de vérité, `SameSite=Lax`)
avec un miroir `localStorage` (`nookConsent`, lecture rapide, tolérance si le
cookie est temporairement indisponible), sous la forme :

```json
{
  "version": 1,
  "configVersion": 3,
  "date": "2026-09-24T15:00:00+02:00",
  "categories": {
    "necessary": true,
    "functional": false,
    "statistics": true,
    "marketing": false
  }
}
```

- `version` : version du **format** de stockage Nook (évolutions techniques du
  plugin lui-même).
- `configVersion` : version de la **configuration de consentement** du site
  (voir section 7) — sert à savoir si un nouveau consentement doit être
  redemandé. Comparaison centralisée dans
  `consent-storage.js#isConsentValid()`, jamais dispersée ailleurs.
- Granularité par catégorie uniquement en V1 (pas par service individuel) :
  suffisant pour les besoins du wizard/centre de préférences actuels: une
  granularité par service pourra être ajoutée plus tard sans rupture (champ
  additif) si un besoin réel apparaît.

## 7. Version de configuration du consentement

- Champ `nook_settings.consent.configVersion` (entier, défaut `1`).
- Incrémentée uniquement lors d'un changement **substantiel** des traitements
  (ajout d'un nouveau service marketing, changement de finalité d'une
  catégorie, etc.), jamais pour un changement purement graphique (couleurs,
  position de la bannière). Ce champ n'est pas encore incrémenté
  automatiquement par l'admin en V1 (à faire manuellement si nécessaire) ;
  une automatisation fine (détection de changement substantiel) est un
  raffinement possible d'une V1.1.
- Quand `configVersion` stocké côté navigateur ne correspond pas à la version
  courante du site, le runtime front (`isConsentValid()`) considère le
  consentement comme expiré et ré-affiche la bannière.

## 8. Administration React (`src/admin/`)

- Un seul point d'entrée `index.js` qui monte `<App />` sur un conteneur admin
  (`#nook-admin-root`), enregistré par `includes/Admin/`.
- `App.jsx` gère le routage interne minimal (écran d'accueil / wizard /
  dashboard) sans dépendance router lourde tant que ce n'est pas nécessaire —
  `@wordpress/element` (`useState`) suffit en V1. Si la navigation se
  complexifie, envisager `@wordpress/router` (vérifier sa stabilité au moment
  de l'introduction).
- **Wizard** (`src/admin/pages/Wizard.jsx` + `src/admin/pages/wizard/*.jsx`) :
  orchestrateur + un composant par étape (`SiteStep`, `ScanStep`,
  `ServicesStep`, `ConsentStep`, `DesignStep`, `PolicyStep`, `FinishStep`).
  La progression (`wizard.currentStep`/`completedSteps`) est persistée côté
  serveur à chaque changement d'étape, ce qui permet une reprise fidèle
  après rafraîchissement de la page.
- **Dashboard** (`src/admin/pages/Dashboard.jsx` + `src/admin/pages/dashboard/*.jsx`) :
  navigation à 6 onglets (`TabPanel`) — Vue d'ensemble, Cookies, Services,
  Bannière, Politique, Paramètres. `BannerPage`/`PolicyPage` réutilisent
  directement `DesignStep`/`PolicyStep` du wizard (pas de duplication
  d'interface) avec un bouton d'enregistrement explicite. `ServicesPage`
  réutilise `ServicesStep` (déjà connecté au store) et ajoute un formulaire
  d'ajout de service personnalisé.
- État global : `@wordpress/data` (`createReduxStore` + `register`) pour le
  store `nook` (réglages, services, cookies, état du scan). Éviter Redux
  brut ou une lib tierce alors que `@wordpress/data` fournit déjà ce besoin.
- Requêtes serveur : `@wordpress/api-fetch`, jamais `fetch` brut (gestion
  native du nonce REST, des erreurs et du middleware).
- i18n : `@wordpress/i18n` (`__`, `_x`, `sprintf`), text domain `nook`.
- Composants : `@wordpress/components` (Button, Card, Panel, ToggleControl,
  ColorPicker, Notice, Spinner, TabPanel, ...) avant tout composant maison.
- Icônes : `@wordpress/icons`.

### Packages `@wordpress/*` retenus (état vérifié à l'initialisation du projet)

| Package | Rôle | Statut vérifié |
|---|---|---|
| `@wordpress/scripts` | Build (webpack + config WP), lint, test | Stable, outil officiel recommandé (`wp-scripts`) |
| `@wordpress/element` | Wrapper React (`createElement`, hooks) | Stable |
| `@wordpress/components` | Composants UI natifs (Button, Card, ToggleControl...) | Stable, cœur de l'admin WP moderne |
| `@wordpress/data` | State management (stores, `useSelect`/`useDispatch`) | Stable, utilisé dans tout le core WP |
| `@wordpress/api-fetch` | Requêtes REST avec nonce/middlewares | Stable |
| `@wordpress/i18n` | Traductions JS | Stable |
| `@wordpress/icons` | Jeu d'icônes SVG WP | Stable |

Ne pas dépendre fortement d'API marquées expérimentales (préfixées
`__experimental` ou `__unstable` dans le code source de Gutenberg) tant qu'un
équivalent stable existe. Si une API expérimentale devient indispensable,
documenter le choix et le risque ici.

`@wordpress/scripts` externalise automatiquement `react`, `react-dom` et les
paquets `@wordpress/*` via `@wordpress/dependency-extraction-webpack-plugin`
(inclus) : le build ne doit **jamais** produire un bundle contenant sa propre
copie de React. Les fichiers `*.asset.php` générés listent les dépendances de
scripts WordPress à déclarer dans `wp_enqueue_script`.

## 9. Front-end public (`src/frontend/`)

- Pas de React. JavaScript natif moderne (ES2020+, compilé/minifié par
  `wp-scripts build` mais sans framework). `@wordpress/i18n` reste utilisé
  pour les quelques libellés fixes de l'UI (pas un framework, externalisé
  vers le handle partagé `wp-i18n` par `@wordpress/scripts`, aucune copie
  embarquée — même principe que pour React).
- Un seul petit bundle chargé sur les pages front uniquement (jamais dans
  l'admin), ~9,5 Ko JS minifié + ~2,4 Ko CSS (build de référence) : bannière,
  centre de préférences, réactivation des scripts/iframes bloqués.
- Modules : `consent-storage.js` (lecture/écriture du consentement),
  `blocker.js` (réactivation des scripts/iframes), `banner.js` (bannière),
  `preferences.js` (centre de préférences, focus trap + Échap), `events.js`
  (petite API d'événements `nook:consent-ready`/`consent-updated`/
  `category-enabled`/`category-disabled`), `dom.js` (utilitaire de création
  d'éléments), `index.js` (orchestrateur).
- La configuration publique (`window.nookFrontend`) est injectée en inline
  par `Nook\Frontend\Assets` à partir de
  `Nook\REST\PublicConfigController::build()` — pas d'appel REST
  supplémentaire à chaque chargement de page (l'endpoint
  `/nook/v1/public-config` reste disponible pour un usage futur).
- Retrait/modification du consentement : lien de pied de page auto-inséré
  (désactivable via `banner.footerLink`) et shortcode
  `[nook_cookie_settings]`, tous deux basés sur un simple attribut
  `data-nook-action="open-preferences"` intercepté par un unique écouteur
  de clic délégué (`index.js`).
- CSS minimal, préfixé `nook-`, sans dépendance à un design system lourd.

## 9.1 Shortcodes (`includes/Frontend/Shortcodes.php`)

- `[nook_cookie_policy]` : rend dynamiquement les catégories de consentement
  et les cookies connus du dernier scan, groupés par catégorie (nom,
  service, finalité, durée). Aucun contenu statique dupliqué qui
  deviendrait obsolète.
- `[nook_cookie_settings]` (attribut optionnel `label`) : bouton rouvrant le
  centre de préférences.
- Lien de pied de page équivalent, automatique et désactivable (voir
  section 9).

## 10. Sécurité

- `current_user_can( 'manage_options' )` (ou capability dédiée future) sur
  toutes les routes d'administration.
- `permission_callback` explicite sur chaque route REST (jamais
  `__return_true` pour une route sensible — seule exception assumée et
  documentée : `/nook/v1/public-config`, strictement en lecture et sans
  aucune donnée sensible).
- `sanitize_text_field`, `sanitize_key`, `esc_html`, `esc_attr`, `esc_url`
  systématiques en entrée/sortie.
- Nonce REST WordPress standard via `@wordpress/api-fetch` (déjà intégré côté
  JS) et vérifié côté PHP par l'infrastructure REST de WordPress.
- Requêtes SQL toujours préparées (`$wpdb->prepare`) si une table custom est
  introduite.
- Aucune exécution de code arbitraire (pas de `eval`, pas de désérialisation
  non sûre de données utilisateur).

## 11. Internationalisation

- Text domain : `nook`.
- Toutes les chaînes visibles (PHP et JS) passent par les fonctions
  officielles (`__()`, `_e()`, `esc_html__()` côté PHP ; `__()` de
  `@wordpress/i18n` côté JS).
- Langues couvertes en V1 : français, anglais. Architecture prête pour plus de
  langues (fichiers `.pot`/`.po`/`.mo` dans `languages/`).

## 12. Accessibilité

- Navigation clavier complète sur la bannière et le centre de préférences.
- Focus visible, gestion du focus trap dans les modales (réutiliser les
  comportements natifs de `@wordpress/components` côté admin ; réplication
  manuelle simple côté front public, sans lib lourde).
- Attributs ARIA sur la bannière (`role="dialog"`, `aria-live` si pertinent).
- Contrastes suffisants par défaut dans les thèmes de bannière fournis.

## 13. Build & tooling

- `@wordpress/scripts` (`wp-scripts`) comme base de build/lint/test.
- Deux entrées de build distinctes : `src/admin/index.js` → `build/admin/` et
  `src/frontend/index.js` → `build/frontend/`, pour ne jamais livrer le code
  admin (React) au visiteur public.
- `npm run start` : build en mode watch pour le développement.
- `npm run build` : build de production (minifié).
- `npm run release` : voir [RELEASE.md](RELEASE.md).
- **Tests PHP** : `php tests/php/run.php` — mini framework maison sans
  dépendance (`tests/php/framework.php`) avec des stubs des fonctions
  WordPress réellement utilisées (`tests/php/bootstrap.php`), pas de
  PHPUnit ni de Composer requis à l'exécution (cohérent avec « aucune
  dépendance Composer obligatoire », voir AGENTS.md). Couvre : `Settings`,
  `Migrations`, `CategoryRegistry`, `CookieDatabase`, `ServiceRegistry`,
  `ServiceState`, `Scanner`, `Blocklist`, `ContentFilter` (blocage réel de
  scripts/iframes), `SettingsController`, `ScanController`,
  `ServicesController`, `CookiesController`, et le bootstrap complet du
  plugin. 60 tests au moment de la rédaction.
- **Tests JS** : à écrire (voir `tests/js/README.md`). `@wordpress/scripts
  test-unit-js` (version installée) requiert Vitest 5 + Vite 8 — une
  tentative d'installation dans cet environnement de développement a
  échoué (bug connu de résolution de peer dependencies dans npm 10.9.2,
  voir `tests/js/README.md` pour le détail et ne pas reproduire la même
  approche sans changer de version de npm).

## 14. Décisions structurelles (journal court)

Cette section liste les décisions importantes prises et leur justification.
À compléter à chaque changement structurel majeur (ne pas y mettre le détail
quotidien, qui va dans `STATUS.md`).

- **2026-09-24** — Choix initial de l'architecture ci-dessus, autoloading
  PSR-4 maison (pas de dépendance Composer runtime), build à deux entrées
  (admin/frontend) avec `@wordpress/scripts`, stockage du consentement
  100% côté navigateur en V1 (pas de preuve de consentement serveur).
- **2026-09-24 (suite)** — Milestone A à E implémentés dans la même session :
  - Renommage `configured` → `setupCompleted` et extension complète du
    schéma de configuration (voir section 3.6) pour couvrir tout le
    parcours wizard, avec migrations de schéma (section 3.7).
  - Correction d'un bug de sécurité/robustesse latent :
    `sanitize_key()` de WordPress met les clés en minuscules, ce qui
    aurait corrompu silencieusement toute clé camelCase envoyée par le
    wizard (`configVersion` → `configversion`, etc.). Remplacé par un
    nettoyage de clé maison préservant la casse
    (`SettingsController::sanitize_key()`), avec test de non-régression
    dédié.
  - `Settings::merge()` remplace `array_replace_recursive()` : une liste
    séquentielle (ex. `wizard.completedSteps`) est désormais **remplacée**
    plutôt que fusionnée par index, sinon elle ne pourrait jamais
    raccourcir une valeur déjà stockée.
  - Scanner serveur réel (`Scanner`, `UrlDiscovery`, `CookieDatabase`,
    `ScanResults`) livré avec le Milestone A plutôt que reporté seul au
    Milestone B, car une étape « Analyse » du wizard qui ne détecterait
    jamais rien (stub) aurait été trompeuse — voir PROJECT.md sur
    l'honnêteté des résultats affichés.
  - Blocage de scripts/iframes (`ContentFilter`, `Blocklist`,
    `Placeholder`) implémenté par réécriture **regex ciblée** de la sortie
    HTML bufferisée, et non par un aller-retour `DOMDocument` →
    `saveHTML()` : ce dernier risquerait de corrompre des pages entières
    (encodage, balises auto-fermantes) alors que Nook ne doit jamais
    casser un site. Les motifs spécifiques à un service
    (`scriptPatterns`/`iframePatterns`) sont vérifiés avant les domaines
    génériques pour désambiguïser les services partageant un domaine (ex.
    Google Analytics vs Google Tag Manager sur `googletagmanager.com`).
  - 7 services V1 complets (Google Analytics, Google Tag Manager, Google
    Ads, Meta Pixel, YouTube, Vimeo, Google Maps), registre extensible via
    filtre, logique d'état partagée (`ServiceState`) entre l'API REST des
    services et le blocage front.
  - `@wordpress/i18n` ajouté au bundle front public (externalisé vers
    `wp-i18n`, jamais bundlé) pour traduire les quelques libellés fixes du
    centre de préférences — décision documentée section 9 car c'est un
    ajout de dépendance au runtime public, à ne pas reproduire sans
    réflexion équivalente.
- **2026-10-15** — Premier test réel (site de Mathieu Panciatici) : garde
  navigateur pour les iframes créées en JavaScript, scripts inline
  construisant une iframe laissés au garde, avertissement Google Fonts
  (sections 3.3 et 3.4).
- **2026-10-15** — Distribution et mises à jour via GitHub (section 15) :
  dépôt public `maxevstudio/nook`, module `Nook\Updater\GitHubUpdater`,
  workflows `.github/workflows/ci.yml` et `release.yml`.

## 15. Mises à jour automatiques (`includes/Updater/`)

Nook n'est pas publié sur WordPress.org. Les sites clients se mettent à jour
depuis les **releases du dépôt public GitHub `maxevstudio/nook`**, via l'écran
natif « Mises à jour » de WordPress (même mécanisme que SimpleTrad).

- `Nook\Updater\GitHubUpdater` interroge
  `https://api.github.com/repos/maxevstudio/nook/releases/latest` (API
  publique, sans jeton), au plus une fois toutes les 6 h (transient
  `nook_github_release`, échecs inclus), et injecte la mise à jour dans
  `site_transient_update_plugins` si la version publiée est supérieure à
  `NOOK_VERSION`.
- Seul un **ZIP joint à la release** est utilisé (dossier racine `nook/`) ;
  le zipball automatique de GitHub n'est jamais proposé car son dossier
  racine ne correspond pas au slug du plugin.
- **Ce n'est pas de la télémétrie** (AGENTS.md règle 9) : la requête ne
  transmet aucune donnée du site (pas d'URL, pas de configuration) — seul
  l'en-tête `User-Agent: Nook-Updater` est envoyé.
- Désactivable : `add_filter( 'nook_github_updater_enabled', '__return_false' );`.
  Dépôt modifiable : filtre `nook_github_repo` (fork, futur dépôt privé de
  distribution Maxev).
- Le ZIP est produit par la CI (`.github/workflows/release.yml`) à chaque
  tag `v*`, via `npm run release -- --package-only` — voir
  [RELEASE.md](RELEASE.md).
