/**
 * Runtime public de Nook (bannière de consentement, centre de préférences,
 * réactivation des scripts/iframes bloqués). Volontairement écrit en
 * JavaScript natif : pas de React côté visiteur (voir AGENTS.md règle 6).
 *
 * `@wordpress/i18n` est utilisé pour les quelques libellés fixes de
 * l'interface (non éditables via le wizard) : c'est un utilitaire de
 * traduction, pas un framework UI, et il est externalisé par
 * `@wordpress/scripts` comme toute dépendance `@wordpress/*` — il ne
 * bundle jamais sa propre copie (voir ARCHITECTURE.md §8, même principe que
 * pour React).
 */
import './style.css';
import {
	clearConsent,
	getConsent,
	isConsentValid,
	setConsent,
} from './consent-storage';
import { activateCategory, replaceIframePlaceholder } from './blocker';
import { removeBanner, renderBanner } from './banner';
import { close as closePreferences, renderPreferences } from './preferences';
import { removeFloatingButton, renderFloatingButton } from './floating-button';
import { dispatchNookEvent } from './events';

( function () {
	'use strict';

	/**
	 * État courant du runtime, exposé pour les intégrations minimales via
	 * `window.nook` (voir PROJECT.md §44).
	 */
	let currentState = null;

	ready( init );

	/**
	 * Exécute `fn` immédiatement si le DOM est déjà prêt, sinon attend
	 * `DOMContentLoaded`.
	 *
	 * @param {() => void} fn Fonction à exécuter.
	 */
	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function init() {
		const config = window.nookFrontend;

		if ( ! config || ! config.categories ) {
			return;
		}

		currentState = { config, consent: null };

		const stored = getConsent();

		if ( isConsentValid( stored, config.consent.configVersion ) ) {
			currentState.consent = stored;
			applyCategories( stored.categories, false );
			dispatchNookEvent( 'nook:consent-ready', { consent: stored } );
			showFloatingButton();
		} else {
			showBanner();
		}

		wireGlobalTriggers();

		window.nook = window.nook || {};
		window.nook.openPreferences = openPreferences;
		window.nook.resetConsent = function () {
			clearConsent();
			window.location.reload();
		};
	}

	/**
	 * Active les catégories acceptées et émet les événements associés.
	 *
	 * @param {Object}  categories { slug: bool }.
	 * @param {boolean} isUpdate   True si ce n'est pas le chargement initial.
	 */
	function applyCategories( categories, isUpdate ) {
		Object.keys( categories ).forEach( ( category ) => {
			if ( categories[ category ] ) {
				activateCategory( category );
				dispatchNookEvent( 'nook:category-enabled', { category } );
			} else if ( isUpdate ) {
				dispatchNookEvent( 'nook:category-disabled', { category } );
			}
		} );
	}

	/**
	 * Construit un jeu de catégories où toutes valent `value`, sauf les
	 * catégories obligatoires qui restent toujours actives.
	 *
	 * @param {Object}  categories Catégories disponibles.
	 * @param {boolean} value      Valeur à appliquer.
	 * @return {Object} { slug: bool }.
	 */
	function allCategories( categories, value ) {
		const result = {};
		Object.keys( categories ).forEach( ( slug ) => {
			result[ slug ] = categories[ slug ].required ? true : value;
		} );
		return result;
	}

	/**
	 * Enregistre un choix de consentement, l'applique, puis referme la
	 * bannière/le centre de préférences.
	 *
	 * @param {Object} categories Catégories choisies.
	 */
	function saveAndApply( categories ) {
		const previous =
			currentState.consent && currentState.consent.categories
				? currentState.consent.categories
				: {};
		const hadPreviousConsent = Boolean( currentState.consent );

		const record = setConsent(
			categories,
			currentState.config.consent.configVersion,
			currentState.config.consent.duration
		);
		currentState.consent = record;

		const revokedSomething = Object.keys( previous ).some(
			( slug ) => previous[ slug ] && ! categories[ slug ]
		);

		removeBanner();
		closePreferences();
		showFloatingButton();

		applyCategories( categories, true );
		dispatchNookEvent( 'nook:consent-updated', { consent: record } );

		if ( hadPreviousConsent && revokedSomething ) {
			// Certains services déjà exécutés ne peuvent pas être arrêtés de
			// façon fiable dynamiquement (minuteurs, connexions déjà
			// établies, pixels déjà déclenchés) : on recharge la page pour
			// garantir qu'ils cessent réellement de fonctionner (voir
			// PROJECT.md §53). Ce comportement est documenté et volontaire.
			window.location.reload();
		}
	}

	/**
	 * Affiche la pastille de réouverture des préférences, sauf si
	 * l'administrateur l'a désactivée (`banner.floatingButton`).
	 */
	function showFloatingButton() {
		if ( false === currentState.config.banner.floatingButton ) {
			return;
		}

		renderFloatingButton( currentState.config.banner, openPreferences );
	}

	function showBanner() {
		removeFloatingButton();
		renderBanner( currentState.config.banner, {
			onAcceptAll: () =>
				saveAndApply(
					allCategories( currentState.config.categories, true )
				),
			onRejectAll: () =>
				saveAndApply(
					allCategories( currentState.config.categories, false )
				),
			onCustomize: openPreferences,
		} );
	}

	function openPreferences() {
		removeBanner();
		removeFloatingButton();

		renderPreferences(
			currentState.config.categories,
			currentState.consent,
			{
				onSave: ( categories ) => saveAndApply( categories ),
				onAcceptAll: () =>
					saveAndApply(
						allCategories( currentState.config.categories, true )
					),
				onRejectAll: () =>
					saveAndApply(
						allCategories( currentState.config.categories, false )
					),
				onClose: showFloatingButton,
			},
			{ closable: Boolean( currentState.consent ) }
		);
	}

	/**
	 * Délègue les clics sur les déclencheurs globaux : réouverture des
	 * préférences (lien de pied de page, shortcode `[nook_cookie_settings]`)
	 * et boutons « Autoriser et afficher » des placeholders d'iframes.
	 */
	function wireGlobalTriggers() {
		document.addEventListener( 'click', ( event ) => {
			const preferencesTrigger = event.target.closest(
				'[data-nook-action="open-preferences"]'
			);

			if ( preferencesTrigger ) {
				event.preventDefault();
				openPreferences();
				return;
			}

			const allowButton = event.target.closest(
				'[data-nook-action="allow"]'
			);

			if ( allowButton ) {
				event.preventDefault();
				handleAllowPlaceholder( allowButton );
			}
		} );
	}

	/**
	 * Traite le clic sur « Autoriser et afficher » d'un placeholder
	 * d'iframe : active uniquement la catégorie concernée, sans recharger
	 * la page (voir PROJECT.md §52).
	 *
	 * @param {HTMLElement} button Bouton cliqué.
	 */
	function handleAllowPlaceholder( button ) {
		const placeholder = button.closest( '.nook-placeholder' );

		if ( ! placeholder ) {
			return;
		}

		const category = placeholder.getAttribute( 'data-nook-category' );

		replaceIframePlaceholder( placeholder );

		if ( ! category ) {
			return;
		}

		const categories =
			currentState.consent && currentState.consent.categories
				? { ...currentState.consent.categories }
				: allCategories( currentState.config.categories, false );

		categories[ category ] = true;

		const record = setConsent(
			categories,
			currentState.config.consent.configVersion,
			currentState.config.consent.duration
		);
		currentState.consent = record;

		activateCategory( category );
		dispatchNookEvent( 'nook:category-enabled', { category } );
		dispatchNookEvent( 'nook:consent-updated', { consent: record } );

		removeBanner();
		showFloatingButton();
	}
} )();
