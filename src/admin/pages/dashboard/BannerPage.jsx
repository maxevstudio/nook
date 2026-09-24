/**
 * Onglet « Bannière » du tableau de bord : réutilise l'éditeur de design du
 * wizard (voir wizard/DesignStep.jsx) hors du parcours d'installation, avec
 * enregistrement explicite.
 */
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';
import DesignStep from '../wizard/DesignStep';

export default function BannerPage() {
	const settings = useSelect(
		( select ) => select( STORE_KEY ).getSettings(),
		[]
	);
	const { saveSettings } = useDispatch( STORE_KEY );

	const [ banner, setBanner ] = useState( settings.banner );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );

	async function handleSave() {
		setIsSaving( true );
		setSaved( false );
		await saveSettings( { banner } );
		setIsSaving( false );
		setSaved( true );
	}

	return (
		<div className="nook-dashboard-page">
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Bannière enregistrée.', 'nook' ) }
				</Notice>
			) }

			<DesignStep
				value={ banner }
				onChange={ ( patch ) => {
					setBanner( { ...banner, ...patch } );
					setSaved( false );
				} }
			/>

			<Button
				variant="primary"
				onClick={ handleSave }
				isBusy={ isSaving }
				disabled={ isSaving }
			>
				{ __( 'Enregistrer', 'nook' ) }
			</Button>
		</div>
	);
}
