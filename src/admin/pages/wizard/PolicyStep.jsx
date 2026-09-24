/**
 * Étape 6 — Politique de cookies : création automatique d'une page,
 * sélection d'une page existante, ou aucune action pour l'instant (voir
 * PROJECT.md §33).
 */
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const MODES = [
	{ label: __( 'Créer automatiquement une page', 'nook' ), value: 'create' },
	{
		label: __( 'Sélectionner une page existante', 'nook' ),
		value: 'existing',
	},
	{ label: __( 'Ne pas créer maintenant', 'nook' ), value: 'none' },
];

export default function PolicyStep( { value, onChange } ) {
	const [ pages, setPages ] = useState( [] );
	const [ isLoadingPages, setIsLoadingPages ] = useState( false );
	const [ loadError, setLoadError ] = useState( false );

	useEffect( () => {
		if ( 'existing' !== value.mode || pages.length > 0 ) {
			return;
		}

		setIsLoadingPages( true );
		apiFetch( {
			path: '/wp/v2/pages?per_page=100&status=publish&_fields=id,title',
		} )
			.then( ( result ) => {
				setPages( result );
				setIsLoadingPages( false );
			} )
			.catch( () => {
				setLoadError( true );
				setIsLoadingPages( false );
			} );
		// La liste des pages n'a besoin d'être rechargée que lorsqu'on
		// bascule vers le mode "existing" pour la première fois.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ value.mode ] );

	return (
		<div className="nook-step nook-step--policy">
			<p className="nook-step__intro">
				{ __(
					'Le shortcode [nook_cookie_policy] affiche une politique de cookies générée dynamiquement à partir des services et cookies détectés par Nook.',
					'nook'
				) }
			</p>

			<RadioControl
				label={ __( 'Politique de cookies', 'nook' ) }
				selected={ value.mode }
				options={ MODES }
				onChange={ ( mode ) => onChange( { mode } ) }
			/>

			{ 'create' === value.mode && (
				<TextControl
					label={ __( 'Titre de la page à créer', 'nook' ) }
					value={ value.title }
					onChange={ ( title ) => onChange( { title } ) }
					__nextHasNoMarginBottom
				/>
			) }

			{ 'existing' === value.mode && (
				<>
					{ loadError && (
						<Notice status="error" isDismissible={ false }>
							{ __(
								'Impossible de charger la liste des pages.',
								'nook'
							) }
						</Notice>
					) }
					<SelectControl
						label={ __(
							'Page contenant la politique de cookies',
							'nook'
						) }
						value={ value.pageId || '' }
						disabled={ isLoadingPages }
						options={ [
							{
								label: __( '— Choisir une page —', 'nook' ),
								value: '',
							},
							...pages.map( ( page ) => ( {
								label: page.title.rendered || `#${ page.id }`,
								value: page.id,
							} ) ),
						] }
						onChange={ ( pageId ) =>
							onChange( {
								pageId: pageId ? Number( pageId ) : null,
							} )
						}
						__nextHasNoMarginBottom
					/>
					<p className="nook-step__meta">
						{ __(
							'Ajoutez le shortcode [nook_cookie_policy] sur cette page pour y afficher la politique.',
							'nook'
						) }
					</p>
				</>
			) }

			{ 'none' === value.mode && (
				<p className="nook-step__meta">
					{ __(
						'Vous pourrez créer ou lier une page de politique de cookies plus tard depuis le tableau de bord.',
						'nook'
					) }
				</p>
			) }
		</div>
	);
}
