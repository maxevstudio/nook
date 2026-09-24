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
│   ├── Admin/                # Menu admin, enqueue des assets React, écrans
│   ├── REST/                 # Contrôleurs REST (un fichier = une ressource)
│   ├── Scanner/              # Scanner serveur (crawl, détection signatures)
│   ├── Consent/              # Modèle de consentement, catégories, versions
│   ├── Services/             # Registre de services (un fichier = un service)
│   ├── Database/             # Accès options / tables custom, migrations
│   └── Frontend/             # Rendu bannière, script blocking, shortcodes
│
├── src/                       # Sources JS/JSX (non distribuées, compilées en build/)
│   ├── admin/                 # Application React de l'administration
│   │   ├── index.js
│   │   ├── App.jsx
│   │   ├── pages/             # Écrans (Onboarding, Wizard, Dashboard, ...)
│   │   ├── components/        # Composants réutilisables spécifiques à Nook
│   │   ├── hooks/              # Hooks React (useSettings, useScan, ...)
│   │   ├── store/              # @wordpress/data (register store "nook")
│   │   └── utils/
│   │
│   └── frontend/              # Runtime public (vanilla JS, sans React)
│       ├── index.js            # Bannière + centre de préférences + blocage
│       └── consent-storage.js  # Lecture/écriture du consentement navigateur
│
├── data/
│   └── cookies.json           # Base de connaissance locale des cookies/services
│
├── assets/                    # Images, icônes statiques non générées par build
├── build/                     # Sortie de @wordpress/scripts (généré, non versionné dans git mais livré en release)
├── languages/                 # Fichiers .pot/.po/.mo
├── tests/
│   ├── php/                   # Tests PHPUnit
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

Le scanner **serveur** :

1. Récupère une liste d'URLs internes à analyser (page d'accueil + pages
   principales, limitées en nombre pour éviter une charge excessive — voir
   `ScanQueue` avec un throttle et une limite configurable, ex. 20 URLs max en
   V1).
2. Pour chaque URL, effectue une requête HTTP (`wp_remote_get`) et analyse le
   HTML retourné :
   - scripts externes (`<script src="...">`) et domaines associés ;
   - iframes externes et domaines associés ;
   - signatures connues (ex. présence de `gtag(`, `fbq(`, `googletagmanager.com`) ;
   - plugins WordPress actifs pouvant impliquer des services tiers (ex. Site
     Kit → GA/GTM potentiels), via `get_option('active_plugins')`.
3. Les cookies **HTTP** (`Set-Cookie` des réponses observées) sont capturés
   côté serveur quand c'est possible. Les cookies **JavaScript** et
   `localStorage`/`sessionStorage` ne sont pas observables depuis PHP : un
   petit script de scan côté navigateur (chargé uniquement pendant l'analyse,
   sur les pages ciblées, avec le consentement implicite de l'administrateur
   qui lance le scan depuis l'admin) complète la détection et renvoie ses
   résultats à l'endpoint REST `POST /nook/v1/scan/results`.
4. Chaque cookie/domaine/script détecté est confronté à `data/cookies.json`
   (Open Cookie Database, voir section 5) pour proposer une catégorie et une
   description. Trois statuts possibles :
   - **Reconnu** — correspondance exacte avec une entrée connue.
   - **Classification suggérée** — correspondance par signature/domaine mais
     pas de fiche cookie précise.
   - **À vérifier** — aucune correspondance, l'administrateur catégorise
     manuellement.
5. Le scanner ne dépend d'aucune infrastructure cloud Maxev : tout tourne sur
   l'installation WordPress elle-même.

### 3.4 Script blocking (`includes/Frontend/`)

Le blocage doit être **réel**, pas seulement visuel :

- Les scripts correspondant à un service non-nécessaire sont interceptés avant
  rendu via les hooks WordPress (`wp_print_scripts`, filtre sur le buffer de
  sortie, ou réécriture des balises `<script>`/`<iframe>` correspondant aux
  patterns du service : `type="text/plain"` + `data-nook-service="ga4"` à la
  place de `type="text/javascript"`/exécution).
- Le runtime front (`src/frontend/index.js`) ne réactive (`type="text/plain"`
  → exécution réelle) un script bloqué qu'après consentement explicite de la
  catégorie correspondante, en relisant dynamiquement le DOM.
- Les iframes tierces (YouTube, Vimeo, Google Maps) sont remplacées par un
  placeholder (`Ce contenu externe nécessite votre consentement. [Autoriser et
  afficher]`) tant que la catégorie du service n'est pas acceptée ; le clic
  déclenche l'octroi du consentement pour ce service précis et charge le vrai
  `src` de l'iframe.
- Le mécanisme doit fonctionner même si le visiteur a JavaScript désactivé
  côté détection initiale : dans ce cas, les scripts restent simplement non
  exécutés (fail-safe = bloqué).

### 3.5 Stockage (`includes/Database/`)

- Réglages plugin (bannière, wizard, config) : `wp_options`, clé
  `nook_settings` (tableau sérialisé/JSON versionné).
- Le consentement des visiteurs **n'est pas stocké côté serveur par défaut**
  (respect du principe « aucune donnée envoyée pour le fonctionnement normal
  ») : il vit côté navigateur (voir section 6). Une preuve de consentement
  serveur pourra être une extension future opt-in, hors scope V1.
- Migrations légères gérées par un simple numéro de schéma dans les options,
  comparé à `NOOK_DB_VERSION`.

## 4. REST API (`includes/REST/`)

Namespace : `nook/v1`.

Endpoints prévus (V1, liste indicative — à tenir à jour ici) :

| Méthode | Route                          | Rôle                                        | Capability |
|---------|---------------------------------|----------------------------------------------|------------|
| GET     | `/nook/v1/settings`             | Lire la configuration courante                | `manage_options` |
| POST    | `/nook/v1/settings`              | Sauvegarder la configuration (wizard/design)  | `manage_options` |
| POST    | `/nook/v1/scan`                  | Démarrer un scan serveur                      | `manage_options` |
| GET     | `/nook/v1/scan/status`           | Suivre l'avancement d'un scan                 | `manage_options` |
| POST    | `/nook/v1/scan/results`          | Recevoir les résultats du scan navigateur      | `manage_options` |
| GET     | `/nook/v1/services`              | Lister les services connus + détectés         | `manage_options` |
| POST    | `/nook/v1/services/:slug`        | Confirmer/corriger un service                  | `manage_options` |
| GET     | `/nook/v1/cookies`               | Lister les cookies détectés/connus             | `manage_options` |
| GET     | `/nook/v1/public-config`         | Config publique servie au runtime front (catégories, textes, design) | public (lecture seule, pas de données sensibles) |

Toute route en écriture applique : `permission_callback` (`current_user_can`),
nonce REST WordPress standard (`X-WP-Nonce`), `sanitize_callback` par
argument, et échappement en sortie. Aucune donnée du navigateur n'est jamais
utilisée sans validation serveur.

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
  standard : `data/cookies.json` est chargé localement à chaque analyse.
- **CookieSearch.org** est une ressource documentaire humaine uniquement :
  aucun scraping automatique, aucune API privée, aucune dépendance technique
  du plugin envers ce site. Une intégration future nécessiterait de vérifier
  au préalable une licence et une API officielle publiées par CookieSearch.

## 6. Stockage du consentement côté navigateur

Consentement mémorisé légèrement (cookie technique propre + miroir
`localStorage` pour lecture rapide par le runtime), sous une forme proche de :

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
  },
  "services": {
    "ga4": true,
    "meta-pixel": false
  }
}
```

- `version` : version du **format** de stockage Nook (évolutions techniques du
  plugin lui-même).
- `configVersion` : version de la **configuration de consentement** du site
  (voir section 7) — sert à savoir si un nouveau consentement doit être
  redemandé.
- `services` : granularité optionnelle par service, au-delà de la catégorie,
  pour les visiteurs qui personnalisent finement (extension possible du
  centre de préférences).

Cette structure est indicative et peut évoluer si une meilleure conception est
trouvée en implémentation — à condition de mettre à jour ce document et
d'incrémenter `version`.

## 7. Version de configuration du consentement

- Option WordPress `nook_consent_config_version` (entier, ex. `3`).
- Incrémentée uniquement lors d'un changement **substantiel** des traitements
  (ajout d'un nouveau service marketing, changement de finalité d'une
  catégorie, etc.), jamais pour un changement purement graphique (couleurs,
  position de la bannière).
- Quand `configVersion` stocké côté navigateur < version courante du site, le
  runtime front considère le consentement comme expiré et ré-affiche la
  bannière.

## 8. Administration React (`src/admin/`)

- Un seul point d'entrée `index.js` qui monte `<App />` sur un conteneur admin
  (`#nook-admin-root`), enregistré par `includes/Admin/`.
- `App.jsx` gère le routage interne minimal (écran d'accueil / wizard /
  dashboard) sans dépendance router lourde tant que ce n'est pas nécessaire —
  `@wordpress/element` (`useState`) suffit en V1. Si la navigation se
  complexifie, envisager `@wordpress/router` (vérifier sa stabilité au moment
  de l'introduction).
- État global : `@wordpress/data` (`createReduxStore` + `register`) pour le
  store `nook` (réglages, résultats de scan, état du wizard). Éviter Redux
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
  `wp-scripts build` mais sans framework).
- Un seul petit bundle chargé sur toutes les pages front (bannière, centre de
  préférences, script blocking runtime), pensé pour être rapide sur mobile.
- CSS minimal, sans dépendance à un design system lourd.

## 10. Sécurité

- `current_user_can( 'manage_options' )` (ou capability dédiée future) sur
  toutes les routes d'administration.
- `permission_callback` explicite sur chaque route REST (jamais
  `__return_true` pour une route sensible).
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

## 14. Décisions structurelles (journal court)

Cette section liste les décisions importantes prises et leur justification.
À compléter à chaque changement structurel majeur (ne pas y mettre le détail
quotidien, qui va dans `STATUS.md`).

- **2026-09-24** — Choix initial de l'architecture ci-dessus, autoloading
  PSR-4 maison (pas de dépendance Composer runtime), build à deux entrées
  (admin/frontend) avec `@wordpress/scripts`, stockage du consentement
  100% côté navigateur en V1 (pas de preuve de consentement serveur).
