/**
 * Garde de blocage des iframes créées en JavaScript (voir ARCHITECTURE.md
 * §3.4, « Blocage dynamique »).
 *
 * Nook\Frontend\ContentFilter neutralise côté serveur les iframes présentes
 * dans le HTML. Mais une iframe créée après coup par un script (ex. lecteur
 * YouTube chargé au clic) n'existe pas encore à ce moment-là. Ce garde,
 * chargé dans le `<head>` avant tout autre script, intercepte donc :
 *
 * 1. l'affectation de `iframe.src` et `iframe.setAttribute( 'src', … )` :
 *    l'URL est mise de côté (`data-nook-src`) avant toute requête réseau ;
 * 2. l'insertion dans la page (MutationObserver) : l'iframe bloquée est
 *    remplacée par le même placeholder que côté serveur. Les iframes
 *    insérées via `innerHTML` (sans passer par le setter) sont rattrapées
 *    ici aussi, en dernier recours.
 *
 * Le runtime principal (index.js) lève le blocage d'une catégorie via
 * `window.nookGuard.allow()` dès que le visiteur y consent.
 *
 * Volontairement autonome (aucun import, aucune dépendance) : il doit
 * s'exécuter le plus tôt possible et rester minuscule.
 */
( function () {
	'use strict';

	const config = window.nookGuardConfig;

	if (
		! config ||
		! config.services ||
		! config.services.length ||
		! window.HTMLIFrameElement
	) {
		return;
	}

	const allowed = readAllowedCategories();
	const srcDescriptor = Object.getOwnPropertyDescriptor(
		window.HTMLIFrameElement.prototype,
		'src'
	);
	const nativeSetAttribute = window.Element.prototype.setAttribute;

	/**
	 * Catégories déjà acceptées (consentement mémorisé valide). Même format
	 * que consent-storage.js : localStorage en priorité, cookie en repli.
	 *
	 * @return {Object} { slug: true }.
	 */
	function readAllowedCategories() {
		let raw = null;

		try {
			raw = window.localStorage.getItem( 'nookConsent' );
		} catch {
			// localStorage indisponible : repli sur le cookie.
		}

		if ( ! raw ) {
			const match = document.cookie.match(
				/(?:^|; )nook_consent=([^;]*)/
			);
			raw = match ? decodeURIComponent( match[ 1 ] ) : null;
		}

		try {
			const record = raw ? JSON.parse( raw ) : null;

			if (
				record &&
				record.categories &&
				record.configVersion === config.configVersion
			) {
				const result = {};
				Object.keys( record.categories ).forEach( ( slug ) => {
					if ( record.categories[ slug ] ) {
						result[ slug ] = true;
					}
				} );
				return result;
			}
		} catch {
			// Consentement illisible : rien n'est accepté.
		}

		return {};
	}

	/**
	 * Service bloqué correspondant à une URL, ou null. Les motifs
	 * spécifiques sont testés avant les domaines, comme côté serveur
	 * (ContentFilter::find_matching_entry()).
	 *
	 * @param {string} url URL de l'iframe.
	 * @return {Object|null} Entrée de service.
	 */
	function findBlockedService( url ) {
		const haystack = String( url || '' ).toLowerCase();

		if ( ! haystack || 0 === haystack.indexOf( 'about:' ) ) {
			return null;
		}

		const matches = ( key ) =>
			config.services.find( ( service ) =>
				( service[ key ] || [] ).some(
					( pattern ) =>
						pattern &&
						-1 !== haystack.indexOf( pattern.toLowerCase() )
				)
			);

		const service = matches( 'iframePatterns' ) || matches( 'domains' );

		return service && ! allowed[ service.category ] ? service : null;
	}

	/**
	 * Met l'URL de côté au lieu de la charger si elle est bloquée.
	 *
	 * @param {HTMLIFrameElement} iframe Iframe concernée.
	 * @param {string}            url    URL demandée.
	 * @return {boolean} True si l'URL a été bloquée.
	 */
	function holdIfBlocked( iframe, url ) {
		const service = findBlockedService( url );

		if ( ! service ) {
			return false;
		}

		nativeSetAttribute.call( iframe, 'data-nook-src', String( url ) );
		nativeSetAttribute.call(
			iframe,
			'data-nook-category',
			service.category
		);
		nativeSetAttribute.call( iframe, 'data-nook-service', service.slug );

		if ( iframe.isConnected ) {
			replaceWithPlaceholder( iframe );
		}

		return true;
	}

	/**
	 * Remplace une iframe bloquée par un placeholder identique à celui
	 * généré côté serveur (Nook\Frontend\Placeholder), que blocker.js sait
	 * déjà réactiver.
	 *
	 * @param {HTMLIFrameElement} iframe Iframe bloquée, présente dans le DOM.
	 */
	function replaceWithPlaceholder( iframe ) {
		const service =
			config.services.find(
				( entry ) =>
					entry.slug === iframe.getAttribute( 'data-nook-service' )
			) || {};
		const placeholder = document.createElement( 'div' );
		const keep = {
			src: iframe.getAttribute( 'data-nook-src' ),
			category: iframe.getAttribute( 'data-nook-category' ),
			service: service.slug || '',
			width: iframe.getAttribute( 'width' ),
			height: iframe.getAttribute( 'height' ),
			title: iframe.getAttribute( 'title' ),
			allow: iframe.getAttribute( 'allow' ),
			class: iframe.getAttribute( 'class' ),
			style: iframe.getAttribute( 'style' ),
		};

		placeholder.className = 'nook-placeholder';
		placeholder.setAttribute( 'role', 'group' );
		placeholder.setAttribute(
			'aria-label',
			keep.title || service.label || ''
		);

		Object.keys( keep ).forEach( ( key ) => {
			if ( keep[ key ] ) {
				placeholder.setAttribute( 'data-nook-' + key, keep[ key ] );
			}
		} );

		const message = document.createElement( 'p' );
		message.className = 'nook-placeholder__message';
		message.textContent = config.i18n.message.replace(
			'%s',
			service.label || ''
		);

		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'nook-placeholder__button';
		button.setAttribute( 'data-nook-action', 'allow' );
		button.textContent = config.i18n.button;

		placeholder.appendChild( message );
		placeholder.appendChild( button );

		mirrorPositioning( iframe, placeholder );

		if ( iframe.parentNode ) {
			iframe.parentNode.replaceChild( placeholder, iframe );
		}
	}

	/**
	 * Une iframe que le thème positionne en absolu (lecteur posé par-dessus
	 * une vignette, conteneur `overflow: hidden`) doit être remplacée à la
	 * même place, sinon le placeholder resterait invisible sous la vignette.
	 *
	 * @param {HTMLIFrameElement} iframe      Iframe bloquée (dans le DOM).
	 * @param {HTMLElement}       placeholder Placeholder de remplacement.
	 */
	function mirrorPositioning( iframe, placeholder ) {
		const computed = window.getComputedStyle( iframe );

		if (
			'absolute' !== computed.position &&
			'fixed' !== computed.position
		) {
			return;
		}

		[
			'position',
			'top',
			'right',
			'bottom',
			'left',
			'width',
			'height',
			'zIndex',
		].forEach( ( property ) => {
			placeholder.style[ property ] = computed[ property ];
		} );
		placeholder.style.boxSizing = 'border-box';
		placeholder.style.minHeight = '0';
	}

	if ( srcDescriptor && srcDescriptor.set ) {
		Object.defineProperty( window.HTMLIFrameElement.prototype, 'src', {
			configurable: true,
			enumerable: srcDescriptor.enumerable,
			get() {
				return srcDescriptor.get.call( this );
			},
			set( value ) {
				if ( ! holdIfBlocked( this, value ) ) {
					srcDescriptor.set.call( this, value );
				}
			},
		} );
	}

	window.Element.prototype.setAttribute = function ( name, value ) {
		if (
			this instanceof window.HTMLIFrameElement &&
			'src' === String( name ).toLowerCase() &&
			holdIfBlocked( this, value )
		) {
			return;
		}

		return nativeSetAttribute.call( this, name, value );
	};

	/**
	 * Inspecte une iframe insérée dans la page.
	 *
	 * @param {HTMLIFrameElement} iframe Iframe insérée.
	 */
	function inspectInsertedIframe( iframe ) {
		if ( iframe.hasAttribute( 'data-nook-src' ) ) {
			if (
				findBlockedService( iframe.getAttribute( 'data-nook-src' ) )
			) {
				replaceWithPlaceholder( iframe );
			}
			return;
		}

		// Insérée avec un `src` déjà présent (innerHTML) : le setter n'a pas
		// été appelé, on la retire avant qu'elle ne termine son chargement.
		const src = iframe.getAttribute( 'src' );

		if ( src && findBlockedService( src ) ) {
			holdIfBlocked( iframe, src );
		}
	}

	new window.MutationObserver( ( mutations ) => {
		mutations.forEach( ( mutation ) => {
			mutation.addedNodes.forEach( ( node ) => {
				if ( 1 !== node.nodeType ) {
					return;
				}

				if ( 'IFRAME' === node.nodeName ) {
					inspectInsertedIframe( node );
				} else if ( node.querySelectorAll ) {
					node.querySelectorAll( 'iframe' ).forEach(
						inspectInsertedIframe
					);
				}
			} );
		} );
	} ).observe( document.documentElement, { childList: true, subtree: true } );

	window.nookGuard = {
		/**
		 * Lève le blocage d'une catégorie (consentement accordé).
		 *
		 * @param {string} category Slug de catégorie.
		 */
		allow( category ) {
			allowed[ category ] = true;
		},
	};
} )();
