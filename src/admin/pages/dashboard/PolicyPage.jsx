/**
 * Onglet « Politique » du tableau de bord : réutilise l'éditeur du wizard
 * (voir wizard/PolicyStep.jsx) et gère la création de la page si nécessaire.
 */
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';
import PolicyStep from '../wizard/PolicyStep';

export default function PolicyPage() {
	const settings = useSelect(
		( select ) => select( STORE_KEY ).getSettings(),
		[]
	);
	const { saveSettings } = useDispatch( STORE_KEY );

	const [ policy, setPolicy ] = useState( settings.policy );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( null );

	async function handleSave() {
		setIsSaving( true );
		setSaved( false );
		setError( null );

		let resolvedPolicy = policy;

		if ( 'create' === policy.mode && ! policy.pageId ) {
			try {
				const page = await apiFetch( {
					path: '/wp/v2/pages',
					method: 'POST',
					data: {
						title: policy.title,
						status: 'publish',
						content: '[nook_cookie_policy]',
					},
				} );
				resolvedPolicy = { ...policy, pageId: page.id };
				setPolicy( resolvedPolicy );
			} catch ( creationError ) {
				setError(
					creationError.message ||
						__( 'La création de la page a échoué.', 'nook' )
				);
				setIsSaving( false );
				return;
			}
		}

		await saveSettings( { policy: resolvedPolicy } );
		setIsSaving( false );
		setSaved( true );
	}

	const pageId = policy.pageId;
	const adminUrl =
		( window.nookAdmin && window.nookAdmin.adminUrl ) || '/wp-admin/';
	const editUrl = pageId
		? `${ adminUrl }post.php?post=${ pageId }&action=edit`
		: null;

	return (
		<div className="nook-dashboard-page">
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Politique de cookies enregistrée.', 'nook' ) }
				</Notice>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<PolicyStep
				value={ policy }
				onChange={ ( patch ) => {
					setPolicy( { ...policy, ...patch } );
					setSaved( false );
				} }
			/>

			{ editUrl && (
				<p>
					<ExternalLink href={ editUrl }>
						{ __(
							'Modifier la page de politique de cookies',
							'nook'
						) }
					</ExternalLink>
				</p>
			) }

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
