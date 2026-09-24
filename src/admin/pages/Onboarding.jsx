/**
 * Écran affiché tant qu'aucune configuration Nook n'existe.
 *
 * Volontairement minimal : pas de dashboard, pas de menus multiples, pas
 * d'upsell (voir PROJECT.md « Expérience à l'installation »).
 */
import { Button, Card, CardBody, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { shield } from '@wordpress/icons';

export default function Onboarding( { onStart } ) {
	return (
		<div className="nook-onboarding">
			<Card className="nook-onboarding__card" size="large">
				<CardBody>
					<div className="nook-onboarding__icon" aria-hidden="true">
						<Icon icon={ shield } size={ 32 } />
					</div>
					<h1 className="nook-onboarding__title">
						{ __(
							'Configurez le consentement de votre site',
							'nook'
						) }
					</h1>
					<p className="nook-onboarding__description">
						{ __(
							'Analysez les cookies et services utilisés par votre site puis configurez votre bannière de consentement.',
							'nook'
						) }
					</p>
					<Button variant="primary" onClick={ onStart }>
						{ __( 'Commencer la configuration', 'nook' ) }
					</Button>
				</CardBody>
			</Card>
		</div>
	);
}
