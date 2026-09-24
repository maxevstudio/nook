/**
 * Onglet « Paramètres » du tableau de bord : réglages avancés restant
 * volontairement peu nombreux (voir PROJECT.md §57).
 */
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	Card,
	CardBody,
	Notice,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';

export default function SettingsPage() {
	const settings = useSelect(
		( select ) => select( STORE_KEY ).getSettings(),
		[]
	);
	const { saveSettings, resetSettings } = useDispatch( STORE_KEY );

	const [ duration, setDuration ] = useState( settings.consent.duration );
	const [ debugEnabled, setDebugEnabled ] = useState(
		Boolean( settings.debug && settings.debug.enabled )
	);
	const [ saved, setSaved ] = useState( false );
	const [ isResetting, setIsResetting ] = useState( false );

	async function handleSave() {
		await saveSettings( {
			consent: { duration },
			debug: { enabled: debugEnabled },
		} );
		setSaved( true );
	}

	async function handleReset() {
		// Action destructive : confirmation explicite requise (voir
		// PROJECT.md §57). `window.confirm()` est le cas exceptionnel
		// documenté (PROJECT.md §99) où une boîte de dialogue native reste
		// justifiée, en l'absence de composant de confirmation stable et
		// non expérimental dans `@wordpress/components`.
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			__(
				'Réinitialiser entièrement la configuration Nook ? Cette action est irréversible.',
				'nook'
			)
		);

		if ( ! confirmed ) {
			return;
		}

		setIsResetting( true );
		await resetSettings();
		setIsResetting( false );
	}

	return (
		<div className="nook-dashboard-page">
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Paramètres enregistrés.', 'nook' ) }
				</Notice>
			) }

			<Card>
				<CardBody>
					<RangeControl
						label={ __(
							'Durée de mémorisation du consentement (jours)',
							'nook'
						) }
						value={ duration }
						min={ 30 }
						max={ 395 }
						onChange={ ( value ) => {
							setDuration( value );
							setSaved( false );
						} }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Mode debug', 'nook' ) }
						help={ __(
							'Affiche des informations de diagnostic dans la console du navigateur (scanner, blocage). Ne jamais activer en production sur un site avec des visiteurs.',
							'nook'
						) }
						checked={ debugEnabled }
						onChange={ ( value ) => {
							setDebugEnabled( value );
							setSaved( false );
						} }
						__nextHasNoMarginBottom
					/>
					<Button variant="primary" onClick={ handleSave }>
						{ __( 'Enregistrer', 'nook' ) }
					</Button>
				</CardBody>
			</Card>

			<Card className="nook-settings__danger-zone">
				<CardBody>
					<h3>{ __( 'Zone de danger', 'nook' ) }</h3>
					<p>
						{ __(
							'Réinitialise entièrement la configuration Nook (site, consentement, bannière, politique, wizard). Les résultats de scan et corrections de cookies/services ne sont pas affectés.',
							'nook'
						) }
					</p>
					<Button
						variant="secondary"
						isDestructive
						onClick={ handleReset }
						isBusy={ isResetting }
						disabled={ isResetting }
					>
						{ __( 'Réinitialiser la configuration', 'nook' ) }
					</Button>
				</CardBody>
			</Card>
		</div>
	);
}
