/**
 * Centre de préférences (modal) : liste les catégories de consentement avec
 * un interrupteur chacune (sauf « Nécessaires », toujours actif) — voir
 * PROJECT.md §43. Implémente un focus trap et la fermeture par Échap
 * (uniquement lorsque la fermeture est autorisée, voir renderPreferences()).
 */
import { __ } from '@wordpress/i18n';
import { el } from './dom';

let previousFocus = null;
let keydownHandler = null;

/**
 * Construit et affiche le centre de préférences.
 *
 * @param {Object}      categories     Catégories de consentement ({ slug: {label, description, required} }).
 * @param {Object|null} currentConsent Consentement actuellement mémorisé, s'il existe.
 * @param {Object}      handlers       { onSave(categories), onAcceptAll(), onRejectAll(), onClose() }.
 * @param {Object}      [options]      { closable: bool } — la fermeture sans choix n'est permise que si un consentement existe déjà.
 * @return {HTMLElement} L'élément racine du centre de préférences.
 */
export function renderPreferences(
	categories,
	currentConsent,
	handlers,
	options
) {
	removeExistingPreferences();

	const settings = options || {};
	// Runtime public vanilla JS (pas de contexte React/iframe éditeur ici,
	// voir AGENTS.md règle 6) : il n'existe pas de "node ref" alternatif.
	// eslint-disable-next-line @wordpress/no-global-active-element
	previousFocus = document.activeElement;

	const toggles = {};

	const categoryNodes = Object.keys( categories ).map( ( slug ) => {
		const category = categories[ slug ];
		const wasAccepted =
			currentConsent && currentConsent.categories
				? Boolean( currentConsent.categories[ slug ] )
				: Boolean( category.enabledByDefault );
		const checked = category.required ? true : wasAccepted;

		const input = el( 'input', {
			type: 'checkbox',
			id: 'nook-pref-' + slug,
			class: 'nook-preferences__toggle',
		} );
		input.checked = checked;
		input.disabled = Boolean( category.required );

		toggles[ slug ] = input;

		return el( 'div', { class: 'nook-preferences__category' }, [
			el( 'div', { class: 'nook-preferences__category-header' }, [
				el( 'label', {
					for: 'nook-pref-' + slug,
					class: 'nook-preferences__category-label',
					text:
						category.label +
						( category.required
							? ' ' + __( '(toujours actif)', 'nook' )
							: '' ),
				} ),
				input,
			] ),
			el( 'p', {
				class: 'nook-preferences__category-description',
				text: category.description,
			} ),
		] );
	} );

	function collectChoices() {
		const choices = {};
		Object.keys( toggles ).forEach( ( slug ) => {
			choices[ slug ] = toggles[ slug ].checked;
		} );
		return choices;
	}

	const closeButton = el( 'button', {
		type: 'button',
		class: 'nook-preferences__close',
		'aria-label': __( 'Fermer', 'nook' ),
		text: '×',
		onClick: () => close( handlers.onClose ),
	} );

	const saveButton = el( 'button', {
		type: 'button',
		class: 'nook-preferences__button nook-preferences__button--primary',
		text: __( 'Enregistrer mes préférences', 'nook' ),
		onClick: () => handlers.onSave( collectChoices() ),
	} );

	const acceptAllButton = el( 'button', {
		type: 'button',
		class: 'nook-preferences__button nook-preferences__button--secondary',
		text: __( 'Tout accepter', 'nook' ),
		onClick: handlers.onAcceptAll,
	} );

	const rejectAllButton = el( 'button', {
		type: 'button',
		class: 'nook-preferences__button nook-preferences__button--secondary',
		text: __( 'Tout refuser', 'nook' ),
		onClick: handlers.onRejectAll,
	} );

	const dialog = el(
		'div',
		{
			id: 'nook-preferences',
			class: 'nook-preferences',
			role: 'dialog',
			'aria-modal': 'true',
			'aria-label': __( 'Centre de préférences des cookies', 'nook' ),
		},
		[
			el( 'div', { class: 'nook-preferences__panel' }, [
				el( 'div', { class: 'nook-preferences__header' }, [
					el( 'h2', {
						class: 'nook-preferences__title',
						text: __(
							'Vos préférences de confidentialité',
							'nook'
						),
					} ),
					settings.closable ? closeButton : null,
				] ),
				el(
					'div',
					{ class: 'nook-preferences__categories' },
					categoryNodes
				),
				el( 'div', { class: 'nook-preferences__actions' }, [
					rejectAllButton,
					acceptAllButton,
					saveButton,
				] ),
			] ),
		]
	);

	const overlay = el( 'div', {
		id: 'nook-preferences-overlay',
		class: 'nook-overlay',
		onClick: () =>
			settings.closable ? close( handlers.onClose ) : undefined,
	} );

	document.body.appendChild( overlay );
	document.body.appendChild( dialog );

	saveButton.focus();

	keydownHandler = ( event ) => {
		if ( 'Escape' === event.key && settings.closable ) {
			close( handlers.onClose );
			return;
		}

		if ( 'Tab' === event.key ) {
			trapFocus( event, dialog );
		}
	};

	document.addEventListener( 'keydown', keydownHandler );

	return dialog;
}

/**
 * Ferme et retire le centre de préférences du document.
 *
 * @param {() => void} [onClose] Callback optionnel appelé après fermeture.
 */
export function close( onClose ) {
	removeExistingPreferences();

	if ( previousFocus && 'function' === typeof previousFocus.focus ) {
		previousFocus.focus();
	}

	previousFocus = null;

	if ( 'function' === typeof onClose ) {
		onClose();
	}
}

function removeExistingPreferences() {
	const existing = document.getElementById( 'nook-preferences' );
	if ( existing ) {
		existing.remove();
	}

	const overlay = document.getElementById( 'nook-preferences-overlay' );
	if ( overlay ) {
		overlay.remove();
	}

	if ( keydownHandler ) {
		document.removeEventListener( 'keydown', keydownHandler );
		keydownHandler = null;
	}
}

/**
 * Piège le focus clavier à l'intérieur de la modale (accessibilité, voir
 * PROJECT.md §72).
 *
 * @param {KeyboardEvent} event  Événement `keydown` (touche Tab).
 * @param {HTMLElement}   dialog Élément racine de la modale.
 */
function trapFocus( event, dialog ) {
	const focusable = dialog.querySelectorAll(
		'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
	);

	if ( 0 === focusable.length ) {
		return;
	}

	const first = focusable[ 0 ];
	const last = focusable[ focusable.length - 1 ];

	// eslint-disable-next-line @wordpress/no-global-active-element -- runtime public vanilla JS, voir la même justification plus haut dans ce fichier.
	const active = document.activeElement;

	if ( event.shiftKey && active === first ) {
		event.preventDefault();
		last.focus();
	} else if ( ! event.shiftKey && active === last ) {
		event.preventDefault();
		first.focus();
	}
}
