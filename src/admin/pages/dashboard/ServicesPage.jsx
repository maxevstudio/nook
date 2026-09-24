/**
 * Onglet « Services » du tableau de bord : réutilise la liste de l'étape
 * wizard (déjà connectée au store) et ajoute la possibilité d'ajouter un
 * service personnalisé (voir PROJECT.md §56).
 */
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import {
	Button,
	Card,
	CardBody,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';
import ServicesStep from '../wizard/ServicesStep';

const CATEGORY_OPTIONS = [
	{ label: __( 'Fonctionnels', 'nook' ), value: 'functional' },
	{ label: __( 'Statistiques', 'nook' ), value: 'statistics' },
	{ label: __( 'Marketing', 'nook' ), value: 'marketing' },
];

function slugify( value ) {
	return value
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /(^-|-$)/g, '' );
}

export default function ServicesPage() {
	const { updateService, fetchServices } = useDispatch( STORE_KEY );
	const [ name, setName ] = useState( '' );
	const [ category, setCategory ] = useState( 'marketing' );
	const [ domains, setDomains ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );

	async function handleAddService() {
		const slug = slugify( name );

		if ( ! slug ) {
			return;
		}

		setIsSaving( true );

		await updateService( slug, {
			name,
			category,
			enabled: true,
			domains: domains
				.split( ',' )
				.map( ( domain ) => domain.trim() )
				.filter( Boolean ),
		} );

		await fetchServices();

		setIsSaving( false );
		setName( '' );
		setDomains( '' );
	}

	return (
		<div className="nook-dashboard-page">
			<ServicesStep />

			<Card className="nook-add-service">
				<CardBody>
					<h3>{ __( 'Ajouter un service personnalisé', 'nook' ) }</h3>
					<TextControl
						label={ __( 'Nom du service', 'nook' ) }
						value={ name }
						onChange={ setName }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Catégorie', 'nook' ) }
						value={ category }
						options={ CATEGORY_OPTIONS }
						onChange={ setCategory }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __(
							'Domaines associés (séparés par des virgules)',
							'nook'
						) }
						value={ domains }
						onChange={ setDomains }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={ handleAddService }
						isBusy={ isSaving }
						disabled={ isSaving || ! name.trim() }
					>
						{ __( 'Ajouter', 'nook' ) }
					</Button>
				</CardBody>
			</Card>
		</div>
	);
}
