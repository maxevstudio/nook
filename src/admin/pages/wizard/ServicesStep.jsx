/**
 * Étape 3 — Services : confirmation/correction des services détectés par le
 * scanner et de leur catégorie (voir PROJECT.md §26/§27).
 */
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Card,
	CardBody,
	Notice,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';

const CATEGORY_OPTIONS = [
	{ label: __( 'Fonctionnels', 'nook' ), value: 'functional' },
	{ label: __( 'Statistiques', 'nook' ), value: 'statistics' },
	{ label: __( 'Marketing', 'nook' ), value: 'marketing' },
];

const STATUS_LABELS = {
	confirmed: __( 'Confirmé', 'nook' ),
	detected: __( 'Détecté automatiquement', 'nook' ),
	to_review: __( 'À vérifier', 'nook' ),
	disabled: __( 'Désactivé', 'nook' ),
};

function ServiceProvenance( { provenance } ) {
	if ( ! provenance || provenance.length === 0 ) {
		return null;
	}

	return (
		<details className="nook-service-card__provenance">
			<summary>
				{ __( 'Pourquoi ce service a-t-il été détecté ?', 'nook' ) }
			</summary>
			<ul>
				{ provenance.map( ( item, index ) => (
					<li key={ index }>{ item.value }</li>
				) ) }
			</ul>
		</details>
	);
}

export default function ServicesStep() {
	const { updateService } = useDispatch( STORE_KEY );
	const { services, isLoading, error } = useSelect( ( select ) => {
		const store = select( STORE_KEY );
		return {
			services: store.getServices(),
			isLoading: store.isLoadingServices(),
			error: store.getServicesError(),
		};
	}, [] );

	if ( isLoading && services.length === 0 ) {
		return (
			<div className="nook-step__loading">
				<Spinner />
				<span>{ __( 'Chargement des services…', 'nook' ) }</span>
			</div>
		);
	}

	return (
		<div className="nook-step nook-step--services">
			<p className="nook-step__intro">
				{ __(
					'Confirmez ou corrigez les services détectés lors de l’analyse. Un service désactivé ne sera jamais chargé par Nook, quelle que soit la catégorie choisie par le visiteur.',
					'nook'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error.message ||
						__( 'Impossible de charger les services.', 'nook' ) }
				</Notice>
			) }

			{ services.length === 0 && (
				<p>
					{ __(
						'Aucun service n’a été détecté pour le moment. Vous pouvez revenir à l’étape « Analyse » pour lancer un scan, ou ajouter un service manuellement plus tard depuis le tableau de bord.',
						'nook'
					) }
				</p>
			) }

			<ul className="nook-service-list">
				{ services.map( ( service ) => (
					<li key={ service.slug } className="nook-service-card">
						<Card>
							<CardBody>
								<div className="nook-service-card__header">
									<div className="nook-service-card__title">
										<strong>{ service.name }</strong>
										<span
											className={ `nook-service-card__status is-${ service.status }` }
										>
											{ STATUS_LABELS[ service.status ] ||
												service.status }
										</span>
									</div>
									<ToggleControl
										label={ __( 'Activé', 'nook' ) }
										checked={ service.enabled }
										onChange={ ( enabled ) =>
											updateService( service.slug, {
												enabled,
											} )
										}
										__nextHasNoMarginBottom
									/>
								</div>
								<p className="nook-service-card__description">
									{ service.description }
								</p>
								<SelectControl
									label={ __( 'Catégorie', 'nook' ) }
									value={ service.category }
									options={ CATEGORY_OPTIONS }
									onChange={ ( category ) =>
										updateService( service.slug, {
											category,
											confirmed: true,
										} )
									}
									__nextHasNoMarginBottom
								/>
								<ServiceProvenance
									provenance={ service.provenance }
								/>
							</CardBody>
						</Card>
					</li>
				) ) }
			</ul>
		</div>
	);
}
