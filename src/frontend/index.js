/**
 * Runtime public de Nook (bannière de consentement, centre de préférences,
 * script blocking). Volontairement écrit en JavaScript natif, sans React,
 * pour rester extrêmement léger sur le front (voir AGENTS.md, règle 6).
 *
 * Ce fichier est un point de départ : la logique complète (blocage réel des
 * scripts, rendu de la bannière, persistance du consentement navigateur) sera
 * développée dans une prochaine étape. Voir ARCHITECTURE.md §9.
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof window.nookFrontend === 'undefined' ) {
			return;
		}

		// Point d'accroche pour la suite du développement (bannière, etc.).
		window.console.debug( 'Nook frontend runtime ready.' );
	} );
} )();
