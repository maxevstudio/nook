/**
 * Étape 4 — Consentement : catégories actives et durée de mémorisation du
 * choix (voir PROJECT.md §28 et §41).
 *
 * Important : aucune catégorie autre que « Nécessaires » ne peut être
 * activée par défaut avant un choix explicite du visiteur — ce n'est donc
 * volontairement pas une option configurable ici (voir PROJECT.md
 * « Catégories de consentement » et AGENTS.md règle 12, absence de dark
 * pattern).
 */
import { RangeControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ConsentStep( { value, onChange } ) {
	const categories = value.categories || {};

	function updateCategory( slug, patch ) {
		onChange( {
			categories: {
				...categories,
				[ slug ]: { ...categories[ slug ], ...patch },
			},
		} );
	}

	return (
		<div className="nook-step nook-step--consent">
			<p className="nook-step__intro">
				{ __(
					'Ajustez les descriptions affichées aux visiteurs pour chaque catégorie de consentement. La catégorie « Nécessaires » reste toujours active : elle ne peut jamais être désactivée par un visiteur.',
					'nook'
				) }
			</p>

			{ Object.values( categories ).map( ( category ) => (
				<div key={ category.slug } className="nook-consent-category">
					<h3>
						{ category.label }
						{ category.required && (
							<span className="nook-consent-category__badge">
								{ __( 'Toujours actif', 'nook' ) }
							</span>
						) }
					</h3>
					<TextareaControl
						label={ __(
							'Description affichée aux visiteurs',
							'nook'
						) }
						value={ category.description }
						onChange={ ( description ) =>
							updateCategory( category.slug, { description } )
						}
						rows={ 2 }
						__nextHasNoMarginBottom
					/>
				</div>
			) ) }

			<RangeControl
				label={ __(
					'Durée de mémorisation du consentement (jours)',
					'nook'
				) }
				value={ value.duration }
				min={ 30 }
				max={ 395 }
				onChange={ ( duration ) => onChange( { duration } ) }
				help={ __(
					'Passé ce délai, la bannière sera de nouveau affichée au visiteur.',
					'nook'
				) }
				__nextHasNoMarginBottom
			/>
		</div>
	);
}
