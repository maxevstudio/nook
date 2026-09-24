/**
 * Réactivation réelle des scripts/iframes bloqués une fois le consentement
 * accordé pour leur catégorie (voir ARCHITECTURE.md « Script blocking » et
 * PROJECT.md §46).
 *
 * Les éléments ont été neutralisés côté serveur par
 * Nook\Frontend\ContentFilter : un `<script type="text/plain"
 * data-nook-src="...">` ou un `.nook-placeholder` à la place d'une iframe.
 * Ce module reconstruit les vrais éléments et les insère dans le DOM.
 */

/**
 * Active tous les scripts et iframes en attente pour une catégorie donnée.
 *
 * @param {string} category Slug de catégorie (ex. `statistics`).
 */
export function activateCategory( category ) {
	activateScripts( category );
	activateIframes( category );
}

/**
 * Réactive les balises `<script>` neutralisées pour une catégorie.
 *
 * @param {string} category Slug de catégorie.
 */
function activateScripts( category ) {
	const nodes = document.querySelectorAll(
		'script[data-nook-category="' + category + '"]'
	);

	nodes.forEach( ( node ) => {
		const replacement = document.createElement( 'script' );

		Array.from( node.attributes ).forEach( ( attr ) => {
			if ( 'type' === attr.name ) {
				return;
			}

			if ( 'data-nook-src' === attr.name ) {
				replacement.setAttribute( 'src', attr.value );
				return;
			}

			if ( 0 === attr.name.indexOf( 'data-nook-' ) ) {
				return;
			}

			replacement.setAttribute( attr.name, attr.value );
		} );

		if ( ! node.hasAttribute( 'data-nook-src' ) ) {
			replacement.textContent = node.textContent;
		}

		node.parentNode.replaceChild( replacement, node );
	} );
}

/**
 * Réactive les iframes neutralisées (placeholders) pour une catégorie.
 *
 * @param {string} category Slug de catégorie.
 */
function activateIframes( category ) {
	const placeholders = document.querySelectorAll(
		'.nook-placeholder[data-nook-category="' + category + '"]'
	);

	placeholders.forEach( ( placeholder ) =>
		replaceIframePlaceholder( placeholder )
	);
}

/**
 * Remplace un placeholder unique par la véritable iframe (utilisé aussi
 * bien en masse par activateIframes() qu'au clic individuel sur « Autoriser
 * et afficher », voir PROJECT.md §51-52).
 *
 * @param {HTMLElement} placeholder Élément `.nook-placeholder`.
 */
export function replaceIframePlaceholder( placeholder ) {
	const src = placeholder.getAttribute( 'data-nook-src' );

	if ( ! src ) {
		return;
	}

	const iframe = document.createElement( 'iframe' );
	iframe.setAttribute( 'src', src );

	const width = placeholder.getAttribute( 'data-nook-width' );
	const height = placeholder.getAttribute( 'data-nook-height' );
	const title = placeholder.getAttribute( 'data-nook-title' );

	if ( width ) {
		iframe.setAttribute( 'width', width );
	}

	if ( height ) {
		iframe.setAttribute( 'height', height );
	}

	if ( title ) {
		iframe.setAttribute( 'title', title );
	}

	iframe.setAttribute( 'allowfullscreen', '' );
	iframe.style.border = '0';
	iframe.style.maxWidth = '100%';

	placeholder.parentNode.replaceChild( iframe, placeholder );
}
