/**
 * Pastille flottante de réouverture des préférences (bas à gauche), affichée
 * une fois le choix fait — voir PROJECT.md « Retrait / modification du
 * consentement ». Sur ordinateur, elle s'allonge au survol pour révéler son
 * libellé ; sur écran tactile, seule l'icône est affichée (voir style.css).
 */
import { __ } from '@wordpress/i18n';
import { el } from './dom';

// Icône dessinée pour Nook (cookie entamé) : constante statique, jamais
// construite à partir de données externes.
const COOKIE_ICON =
	'<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' +
	'<path d="M12 2.75a9.25 9.25 0 1 0 9.25 9.4 3.1 3.1 0 0 1-3.5-3.2 3.1 3.1 0 0 1-3.2-3.5A3.1 3.1 0 0 1 12 2.75z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>' +
	'<circle cx="8.6" cy="10.2" r="1.25" fill="currentColor"/>' +
	'<circle cx="9.6" cy="15.4" r="1.25" fill="currentColor"/>' +
	'<circle cx="14.6" cy="14.2" r="1.25" fill="currentColor"/>' +
	'</svg>';

/**
 * Affiche la pastille (sans doublon).
 *
 * @param {Object}     banner  Configuration de la bannière (couleurs).
 * @param {() => void} onClick Ouverture du centre de préférences.
 */
export function renderFloatingButton( banner, onClick ) {
	removeFloatingButton();

	const label = __( 'Préférences cookies', 'nook' );
	const icon = el( 'span', { class: 'nook-floating__icon' } );
	icon.innerHTML = COOKIE_ICON;

	document.body.appendChild(
		el(
			'button',
			{
				type: 'button',
				id: 'nook-floating',
				class: 'nook-floating',
				'aria-label': label,
				style: {
					backgroundColor: banner.backgroundColor,
					color: banner.textColor,
				},
				onClick,
			},
			[
				icon,
				el( 'span', {
					class: 'nook-floating__label',
					'aria-hidden': 'true',
					text: label,
				} ),
			]
		)
	);
}

/**
 * Retire la pastille (bannière ou centre de préférences ouvert).
 */
export function removeFloatingButton() {
	const existing = document.getElementById( 'nook-floating' );

	if ( existing ) {
		existing.remove();
	}
}
