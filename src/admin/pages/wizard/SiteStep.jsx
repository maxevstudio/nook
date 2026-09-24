/**
 * Étape 1 — Site : informations de base (pays, langue, type de site).
 * Reste volontairement simple (voir PROJECT.md §8).
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const COUNTRIES = [
	{ label: __( 'France', 'nook' ), value: 'FR' },
	{ label: __( 'Belgique', 'nook' ), value: 'BE' },
	{ label: __( 'Suisse', 'nook' ), value: 'CH' },
	{ label: __( 'Autre pays de l’Union européenne', 'nook' ), value: 'EU' },
	{ label: __( 'Hors Union européenne', 'nook' ), value: 'OTHER' },
];

const LANGUAGES = [
	{ label: __( 'Français', 'nook' ), value: 'fr' },
	{ label: __( 'Anglais', 'nook' ), value: 'en' },
];

const SITE_TYPES = [
	{ label: __( 'Site vitrine', 'nook' ), value: 'business' },
	{ label: __( 'E-commerce', 'nook' ), value: 'ecommerce' },
	{ label: __( 'Autre', 'nook' ), value: 'other' },
];

export default function SiteStep( { value, onChange } ) {
	return (
		<div className="nook-step nook-step--site">
			<p className="nook-step__intro">
				{ __(
					'Ces informations aident Nook à proposer une configuration de consentement adaptée. La V1 cible principalement la France et l’Union européenne (RGPD/ePrivacy).',
					'nook'
				) }
			</p>

			<SelectControl
				label={ __( 'Pays principal', 'nook' ) }
				value={ value.country }
				options={ COUNTRIES }
				onChange={ ( country ) => onChange( { country } ) }
				__nextHasNoMarginBottom
			/>

			<SelectControl
				label={ __( 'Langue par défaut', 'nook' ) }
				value={ value.language }
				options={ LANGUAGES }
				onChange={ ( language ) => onChange( { language } ) }
				__nextHasNoMarginBottom
			/>

			<SelectControl
				label={ __( 'Type de site', 'nook' ) }
				value={ value.type }
				options={ SITE_TYPES }
				onChange={ ( type ) => onChange( { type } ) }
				__nextHasNoMarginBottom
			/>
		</div>
	);
}
