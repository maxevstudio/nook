# PROJECT.md — Cahier des charges produit

## Qu'est-ce que Nook ?

Nook est un gestionnaire de consentement aux cookies (« Consent Management
Platform ») léger, moderne, indépendant et WordPress-natif.

Nook n'a pas pour ambition de reproduire l'intégralité des fonctionnalités de
Complianz, CookieYes ou Cookiebot. L'objectif est une expérience volontairement
resserrée :

```
installer → analyser → configurer → publier
```

## Philosophie produit

Nook doit être **simple, silencieux et autonome**.

Après installation et activation, le plugin doit pouvoir fonctionner
immédiatement, sans étape bloquante côté serveur externe.

Nook n'impose jamais :

- de compte Nook ;
- de compte Maxev ;
- d'adresse email obligatoire ;
- d'inscription ;
- de newsletter ;
- de SaaS obligatoire ;
- de publicité ;
- d'upsell ;
- de dashboard marketing ;
- de système de licence obligatoire pour les fonctionnalités principales ;
- de dépendance cloud pour fonctionner.

Aucune donnée du site (URL, configuration, cookies détectés, contenu) n'est
envoyée vers Maxev ou un tiers pour le fonctionnement normal du plugin. Voir la
règle télémétrie dans [AGENTS.md](AGENTS.md).

## Expérience à l'installation

Quand aucune configuration n'existe encore, l'admin affiche un écran unique et
minimal — pas un dashboard complexe, pas de multiples menus, pas de
notifications, pas d'upsell :

```
Configurez le consentement de votre site

Analysez les cookies et services utilisés par votre site puis configurez
votre bannière de consentement.

[ Commencer la configuration ]
```

## Assistant initial (wizard)

Le bouton lance un assistant React en 7 étapes :

1. **Site** — informations de base (nom, langue par défaut, pays ciblés).
2. **Analyse** — lancement du scanner et présentation des résultats bruts.
3. **Services** — confirmation/correction des services détectés et de leur
   catégorie.
4. **Consentement** — choix des catégories actives, textes, comportement par
   défaut.
5. **Design** — apparence de la bannière (couleurs, position, rayon, logo).
6. **Politique de cookies** — génération/liaison de la page de politique.
7. **Finalisation** — récapitulatif et publication.

L'utilisateur doit pouvoir avancer, revenir en arrière, sauvegarder sa
progression (persistée côté serveur via REST, pas seulement en mémoire) et
reprendre l'assistant plus tard exactement où il l'a laissé.

## Périmètre juridique initial (V1)

La V1 cible principalement :

- France
- Union européenne (RGPD / ePrivacy)

L'architecture (catégories, registre de services, moteur de blocage) reste
générique et extensible à d'autres régimes juridiques, mais **la V1 ne
développe pas tous les régimes du monde**. Pas d'usine à gaz.

## Catégories de consentement

Catégories minimales, extensibles :

- **Nécessaires** — toujours actifs, jamais soumis à consentement.
- **Fonctionnels**
- **Statistiques**
- **Marketing**

Toute catégorie autre que « Nécessaires » reste inactive tant que le visiteur
n'a pas fait de choix explicite.

## Bannière de consentement

Actions minimales proposées, sur un pied d'égalité visuelle et fonctionnelle :

- **Tout accepter**
- **Tout refuser**
- **Personnaliser**

Aucun dark pattern : le bouton « Refuser » ne doit jamais être plus petit,
moins contrasté, plus lent d'accès ou caché derrière un clic supplémentaire par
rapport à « Accepter ».

## Centre de préférences

Ouvert par « Personnaliser », il liste chaque catégorie avec une explication
claire, un interrupteur (sauf « Nécessaires », toujours actif) et,
optionnellement, la liste des services concernés par catégorie. Se termine par
« Enregistrer mes préférences ».

## Retrait / modification du consentement

Le visiteur doit pouvoir rouvrir ses préférences à tout moment via :

- un lien dans le footer (inséré automatiquement, désactivable) ;
- un bouton flottant optionnel ;
- le shortcode `[nook_cookie_settings]`.

Un bloc Gutenberg équivalent est envisageable plus tard (voir hors scope).

## Politique de cookies

Shortcode `[nook_cookie_policy]` généré dynamiquement à partir des services et
cookies actifs (nom, service, finalité, durée, catégorie). Nook est présenté
comme un **outil technique facilitant** la conformité, pas comme une garantie
juridique absolue — ce point doit rester visible dans les textes d'aide de
l'admin et la documentation utilisateur.

## Hors scope V1

Explicitement non prioritaires pour cette phase du projet :

- A/B testing
- analytics marketing
- cloud Maxev
- SaaS
- marketplace
- compte utilisateur Nook
- licence commerciale
- IA
- couverture de tous les pays du monde
- TCF (Transparency & Consent Framework) publicitaire complexe
- reporting avancé

## Priorités V1 (résumé)

Bootstrap WordPress · architecture moderne · admin React · composants natifs
WordPress · assistant initial · catégories · bannière · accepter/refuser/
personnaliser · stockage du consentement · centre de préférences · retrait du
consentement · script blocking · services majeurs · scanner initial · Open
Cookie Database · UI cookies · UI services · politique dynamique · design
simple · release automatisée · ZIP propre.

Détails techniques : voir [ARCHITECTURE.md](ARCHITECTURE.md). État d'avancement
réel : voir [STATUS.md](STATUS.md).
