/**
 * Composant racine de l'administration Nook.
 *
 * La navigation reste volontairement simple (état local) : pas de router
 * lourd tant que ce n'est pas nécessaire (voir ARCHITECTURE.md §8).
 */
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from './store';
import Onboarding from './pages/Onboarding';
import Wizard from './pages/Wizard';
import Dashboard from './pages/Dashboard';

export default function App() {
	const [ view, setView ] = useState( 'onboarding' );

	const { isConfigured, isLoading, hasResolved } = useSelect( ( select ) => {
		const store = select( STORE_KEY );
		return {
			isConfigured: store.isConfigured(),
			isLoading: store.isLoading(),
			hasResolved: store.hasFinishedResolution( 'getSettings', [] ),
		};
	}, [] );

	if ( isLoading && ! hasResolved ) {
		return (
			<div className="nook-app__loading">
				<Spinner />
				<span>{ __( 'Chargement…', 'nook' ) }</span>
			</div>
		);
	}

	if ( view === 'wizard' ) {
		return <Wizard onExit={ () => setView( 'onboarding' ) } />;
	}

	if ( isConfigured ) {
		return <Dashboard />;
	}

	return <Onboarding onStart={ () => setView( 'wizard' ) } />;
}
