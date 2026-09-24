/**
 * Point d'entrée de l'application React d'administration de Nook.
 *
 * WordPress fournit déjà React/ReactDOM via `@wordpress/element` : on ne
 * bundle jamais notre propre copie de ces bibliothèques (voir AGENTS.md).
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import './style.css';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'nook-admin-root' );

	if ( ! container ) {
		return;
	}

	const root = createRoot( container );
	root.render( <App /> );
} );
