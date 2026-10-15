/**
 * Composant racine de l'administration Nook.
 *
 * La navigation reste volontairement simple (état local) : pas de router
 * lourd tant que ce n'est pas nécessaire (voir ARCHITECTURE.md §8).
 */
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from './store';
import Onboarding from './pages/Onboarding';
import Wizard from './pages/Wizard';
import Dashboard from './pages/Dashboard';

export default function App() {
	const [ view, setView ] = useState( 'onboarding' );

	// `getSettings()` doit être appelé ici : c'est cet appel qui déclenche
	// son resolver (chargement REST). Sans lui, les réglages ne sont jamais
	// chargés et le wizard/dashboard reçoivent `null`.
	const { settings, isSetupCompleted, error } = useSelect( ( select ) => {
		const store = select( STORE_KEY );
		return {
			settings: store.getSettings(),
			isSetupCompleted: store.isSetupCompleted(),
			error: store.getError(),
		};
	}, [] );

	if ( ! settings && error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error.message ||
					__(
						'Impossible de charger la configuration de Nook.',
						'nook'
					) }
			</Notice>
		);
	}

	if ( ! settings ) {
		return (
			<div className="nook-app__loading">
				<Spinner />
				<span>{ __( 'Chargement…', 'nook' ) }</span>
			</div>
		);
	}

	function returnToDashboard() {
		setView( 'onboarding' );
	}

	if ( view === 'wizard' ) {
		return (
			<Wizard
				onExit={ returnToDashboard }
				onComplete={ returnToDashboard }
			/>
		);
	}

	if ( isSetupCompleted ) {
		return <Dashboard />;
	}

	return <Onboarding onStart={ () => setView( 'wizard' ) } />;
}
