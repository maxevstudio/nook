/**
 * Coquille du tableau de bord affiché une fois la configuration terminée.
 *
 * Implémentation complète à venir — voir PROJECT.md « Dashboard après
 * configuration » pour la structure cible (Vue d'ensemble, Cookies,
 * Services, Bannière, Politique, Paramètres) et STATUS.md pour l'avancement.
 */
import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Dashboard() {
	return (
		<div className="nook-dashboard">
			<Card>
				<CardBody>
					<h1 className="nook-dashboard__title">
						{ __( 'Nook', 'nook' ) }
					</h1>
					<p>
						{ __(
							'Votre consentement est configuré. Le tableau de bord complet (cookies, services, bannière, politique) est en cours de construction.',
							'nook'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
