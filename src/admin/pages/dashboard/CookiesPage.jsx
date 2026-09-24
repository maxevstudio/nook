/**
 * Onglet « Cookies » du tableau de bord : liste les cookies détectés par le
 * scanner et les corrections manuelles, avec recherche et filtres (voir
 * PROJECT.md §20-22).
 */
import { useMemo, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { SelectControl, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';

const CATEGORY_OPTIONS = [
	{ label: __( 'Toutes les catégories', 'nook' ), value: '' },
	{ label: __( 'Nécessaires', 'nook' ), value: 'necessary' },
	{ label: __( 'Fonctionnels', 'nook' ), value: 'functional' },
	{ label: __( 'Statistiques', 'nook' ), value: 'statistics' },
	{ label: __( 'Marketing', 'nook' ), value: 'marketing' },
];

const EDITABLE_CATEGORY_OPTIONS = CATEGORY_OPTIONS.slice( 1 );

const STATUS_OPTIONS = [
	{ label: __( 'Tous les statuts', 'nook' ), value: '' },
	{ label: __( 'Reconnu', 'nook' ), value: 'recognized' },
	{ label: __( 'Classification suggérée', 'nook' ), value: 'suggested' },
	{ label: __( 'À vérifier', 'nook' ), value: 'to_review' },
	{ label: __( 'Ignoré', 'nook' ), value: 'ignored' },
];

export default function CookiesPage() {
	const { updateCookie } = useDispatch( STORE_KEY );
	const { cookies, isLoading } = useSelect( ( select ) => {
		const store = select( STORE_KEY );
		return {
			cookies: store.getCookies(),
			isLoading: store.isLoadingCookies(),
		};
	}, [] );

	const [ search, setSearch ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( '' );

	const filtered = useMemo( () => {
		const query = search.trim().toLowerCase();

		return cookies.filter( ( cookie ) => {
			if ( categoryFilter && cookie.category !== categoryFilter ) {
				return false;
			}
			if ( statusFilter && cookie.status !== statusFilter ) {
				return false;
			}
			if (
				query &&
				-1 === cookie.name.toLowerCase().indexOf( query ) &&
				-1 === ( cookie.service || '' ).toLowerCase().indexOf( query )
			) {
				return false;
			}
			return true;
		} );
	}, [ cookies, search, categoryFilter, statusFilter ] );

	if ( isLoading && 0 === cookies.length ) {
		return (
			<div className="nook-step__loading">
				<Spinner />
				<span>{ __( 'Chargement des cookies…', 'nook' ) }</span>
			</div>
		);
	}

	return (
		<div className="nook-dashboard-page">
			<div className="nook-cookies__filters">
				<TextControl
					label={ __( 'Rechercher', 'nook' ) }
					value={ search }
					onChange={ setSearch }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Catégorie', 'nook' ) }
					value={ categoryFilter }
					options={ CATEGORY_OPTIONS }
					onChange={ setCategoryFilter }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Statut', 'nook' ) }
					value={ statusFilter }
					options={ STATUS_OPTIONS }
					onChange={ setStatusFilter }
					__nextHasNoMarginBottom
				/>
			</div>

			{ 0 === filtered.length && (
				<p>
					{ __(
						'Aucun cookie ne correspond à ces critères.',
						'nook'
					) }
				</p>
			) }

			{ filtered.length > 0 && (
				<table className="nook-cookies__table">
					<thead>
						<tr>
							<th>{ __( 'Nom', 'nook' ) }</th>
							<th>{ __( 'Service', 'nook' ) }</th>
							<th>{ __( 'Catégorie', 'nook' ) }</th>
							<th>{ __( 'Durée', 'nook' ) }</th>
							<th>{ __( 'Statut', 'nook' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ filtered.map( ( cookie ) => (
							<tr key={ cookie.id }>
								<td>{ cookie.name }</td>
								<td>{ cookie.service || '—' }</td>
								<td>
									<SelectControl
										value={ cookie.category || '' }
										options={ [
											{
												label: __( '—', 'nook' ),
												value: '',
											},
											...EDITABLE_CATEGORY_OPTIONS,
										] }
										onChange={ ( category ) =>
											updateCookie( cookie.id, {
												category,
											} )
										}
										__nextHasNoMarginBottom
									/>
								</td>
								<td>{ cookie.retention || '—' }</td>
								<td>
									<SelectControl
										value={ cookie.status }
										options={ STATUS_OPTIONS.filter(
											( option ) => option.value
										) }
										onChange={ ( status ) =>
											updateCookie( cookie.id, {
												status,
											} )
										}
										__nextHasNoMarginBottom
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
