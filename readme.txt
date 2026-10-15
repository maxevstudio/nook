=== Nook ===
Contributors: maxev
Tags: cookies, consent, rgpd, gdpr, privacy
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestionnaire de consentement aux cookies léger, moderne et WordPress-natif. Installer → analyser → configurer → publier.

== Description ==

Nook est un gestionnaire de consentement aux cookies (CMP) simple, silencieux
et autonome. Après installation et activation, il fonctionne immédiatement,
sans compte, sans email et sans SaaS obligatoire.

**Fonctionnalités principales (V1)**

* Assistant de configuration en 7 étapes (Site, Analyse, Services,
  Consentement, Design, Politique de cookies, Finalisation)
* Bannière de consentement (Tout accepter / Tout refuser / Personnaliser),
  sans dark pattern
* Centre de préférences par catégorie (Nécessaires, Fonctionnels,
  Statistiques, Marketing)
* Blocage réel des scripts et iframes tiers avant consentement (Google
  Analytics/GA4, Google Tag Manager, Google Ads, Meta Pixel, YouTube, Vimeo,
  Google Maps)
* Scanner de site local, sans infrastructure cloud
* Base de connaissance locale des cookies dérivée de l'Open Cookie Database
* Politique de cookies générée dynamiquement (`[nook_cookie_policy]`)
* Shortcode de retrait du consentement (`[nook_cookie_settings]`)
* Administration 100% React, avec les composants natifs WordPress

Nook n'impose aucun compte, aucun email, aucune télémétrie et aucune
publicité dans l'administration.

Ce plugin est un outil technique facilitant la gestion du consentement ; il
ne constitue pas un conseil juridique.

== Installation ==

1. Téléversez le dossier `nook` dans `/wp-content/plugins/`, ou installez le
   ZIP directement depuis **Extensions → Ajouter**.
2. Activez le plugin depuis le menu **Extensions**.
3. Rendez-vous dans le menu **Nook** de l'administration et suivez
   l'assistant de configuration.

== Frequently Asked Questions ==

= Dois-je créer un compte pour utiliser Nook ? =

Non. Nook fonctionne entièrement sur votre installation WordPress.

= Nook envoie-t-il des données à un service externe ? =

Non, par défaut. Aucune télémétrie n'est activée.

== Changelog ==

Voir CHANGELOG.md dans le dépôt source pour l'historique complet.

= 0.1.0-dev =
* Fondations du projet (documentation, architecture, squelette du plugin).
