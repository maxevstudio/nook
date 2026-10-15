/**
 * Petit utilitaire de création d'éléments DOM, pour éviter la répétition de
 * `document.createElement`/`setAttribute` dans banner.js/preferences.js.
 * Reste volontairement minimal (pas de veritable moteur de template) : le
 * runtime public de Nook n'embarque aucun framework (voir AGENTS.md règle 6).
 */

/**
 * Crée un élément DOM.
 *
 * @param {string}             tag        Nom de balise.
 * @param {Object}             [attrs]    Attributs, styles (`style`), texte (`text`) ou gestionnaires (`onClick`, ...).
 * @param {Array<Node|string>} [children] Enfants à ajouter (ignorés si `text` est fourni).
 * @return {HTMLElement} L'élément créé.
 */
export function el( tag, attrs, children ) {
	const node = document.createElement( tag );

	Object.keys( attrs || {} ).forEach( ( key ) => {
		const value = attrs[ key ];

		if ( 'text' === key ) {
			node.textContent = value;
		} else if ( 'style' === key && value && 'object' === typeof value ) {
			Object.keys( value ).forEach( ( property ) => {
				// Propriétés personnalisées CSS (`--nook-…`) : setProperty()
				// est la seule façon de les définir en inline.
				if ( 0 === property.indexOf( '--' ) ) {
					node.style.setProperty( property, value[ property ] );
				} else {
					node.style[ property ] = value[ property ];
				}
			} );
		} else if ( 0 === key.indexOf( 'on' ) && 'function' === typeof value ) {
			node.addEventListener( key.slice( 2 ).toLowerCase(), value );
		} else if ( undefined !== value && null !== value && false !== value ) {
			node.setAttribute( key, value );
		}
	} );

	( children || [] ).forEach( ( child ) => {
		if ( ! child ) {
			return;
		}

		node.appendChild(
			'string' === typeof child ? document.createTextNode( child ) : child
		);
	} );

	return node;
}
