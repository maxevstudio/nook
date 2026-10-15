/**
 * Avertissements de conformité remontés par le scanner qui ne relèvent pas
 * de la bannière (voir Nook\Scanner\Scanner::build_warnings()). Partagé
 * entre l'étape « Analyse » du wizard et la vue d'ensemble du dashboard.
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ScanWarnings( { scanner } ) {
	const warnings = ( scanner && scanner.warnings ) || [];
	const hasGoogleFonts = warnings.some(
		( warning ) => 'google-fonts' === warning.code
	);

	if ( ! hasGoogleFonts ) {
		return null;
	}

	return (
		<Notice status="warning" isDismissible={ false }>
			<p>
				<strong>
					{ __(
						'Polices Google Fonts chargées depuis les serveurs de Google',
						'nook'
					) }
				</strong>
			</p>
			<p>
				{ __(
					'Ce n’est pas un cookie, et la bannière ne peut pas le bloquer : l’adresse IP de chaque visiteur est transmise à Google, ce qui a déjà été jugé contraire au RGPD. Hébergez les polices sur votre site : avec Elementor, activez « Charger les polices Google localement » (Elementor → Réglages → Performance) ; sinon, utilisez votre thème ou une extension dédiée.',
					'nook'
				) }
			</p>
		</Notice>
	);
}
