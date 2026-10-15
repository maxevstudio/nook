/**
 * Bannière de consentement publique (voir PROJECT.md §29 et AGENTS.md règle
 * 12 : « Tout accepter »/« Tout refuser » sur un pied d'égalité visuelle et
 * fonctionnelle, aucun dark pattern).
 */
import { el } from './dom';

/**
 * Construit et insère la bannière dans le document.
 *
 * @param {Object} banner   Configuration de la bannière (textes, design).
 * @param {Object} handlers { onAcceptAll, onRejectAll, onCustomize }.
 * @return {HTMLElement} L'élément racine de la bannière (pour pouvoir la retirer).
 */
export function renderBanner( banner, handlers ) {
	removeExistingBanner();

	const buttonBaseStyle = {
		padding: '10px 20px',
		borderRadius: `${ banner.radius }px`,
		fontSize: '14px',
		fontWeight: '600',
		cursor: 'pointer',
		border: '2px solid transparent',
	};

	const acceptButton = el( 'button', {
		type: 'button',
		class: 'nook-banner__button nook-banner__button--accept',
		text: banner.buttons.acceptAll,
		style: Object.assign(
			{},
			buttonBaseStyle,
			'outline' === banner.buttonStyle
				? {
						background: 'transparent',
						color: banner.accentColor,
						borderColor: banner.accentColor,
					}
				: {
						background: banner.accentColor,
						color: '#ffffff',
						borderColor: banner.accentColor,
					}
		),
		onClick: handlers.onAcceptAll,
	} );

	const rejectButton = el( 'button', {
		type: 'button',
		class: 'nook-banner__button nook-banner__button--reject',
		text: banner.buttons.rejectAll,
		style: Object.assign( {}, buttonBaseStyle, {
			background: 'transparent',
			color: banner.textColor,
			borderColor: banner.textColor,
		} ),
		onClick: handlers.onRejectAll,
	} );

	const customizeButton = el( 'button', {
		type: 'button',
		class: 'nook-banner__button nook-banner__button--customize',
		text: banner.buttons.customize,
		style: {
			background: 'transparent',
			border: 'none',
			textDecoration: 'underline',
			color: banner.textColor,
			cursor: 'pointer',
			fontSize: '14px',
			padding: '10px 4px',
		},
		onClick: handlers.onCustomize,
	} );

	const content = el( 'div', { class: 'nook-banner__content' }, [
		el( 'strong', {
			class: 'nook-banner__title',
			text: banner.title,
		} ),
		el( 'p', { class: 'nook-banner__text', text: banner.text } ),
	] );

	const actions = el( 'div', { class: 'nook-banner__actions' }, [
		rejectButton,
		customizeButton,
		acceptButton,
	] );

	const root = el(
		'div',
		{
			id: 'nook-banner',
			class: `nook-banner nook-banner--${ banner.position } nook-banner--${ banner.width }`,
			role: 'region',
			'aria-label': banner.title,
			style: {
				backgroundColor: banner.backgroundColor,
				color: banner.textColor,
				// Variable plutôt que `border-radius` inline : sur mobile, la
				// bannière devient un panneau collé au bas de l'écran dont
				// seuls les coins supérieurs sont arrondis (voir style.css).
				'--nook-radius': `${ banner.radius }px`,
			},
		},
		[ content, actions ]
	);

	if ( banner.overlay ) {
		document.body.appendChild(
			el( 'div', { id: 'nook-overlay', class: 'nook-overlay' } )
		);
	}

	document.body.appendChild( root );

	acceptButton.focus();

	return root;
}

/**
 * Retire la bannière (et l'overlay éventuel) du document.
 */
export function removeBanner() {
	removeExistingBanner();
}

function removeExistingBanner() {
	const existing = document.getElementById( 'nook-banner' );
	if ( existing ) {
		existing.remove();
	}

	const overlay = document.getElementById( 'nook-overlay' );
	if ( overlay ) {
		overlay.remove();
	}
}
