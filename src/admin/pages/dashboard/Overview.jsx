/**
 * Onglet « Vue d'ensemble » du tableau de bord (voir PROJECT.md §37).
 *
 * Reste volontairement sobre : aucun score marketing, aucune gamification.
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';

/**
 * Formate une date ISO en date lisible localement, ou un texte de repli si
 * aucune analyse n'a encore été effectuée.
 *
 * @param {string|null} isoDate Date ISO ou null.
 * @return {string} Date lisible localement, ou texte de repli.
 */
function formatDate( isoDate ) {
	if ( ! isoDate ) {
		return __( 'Jamais', 'nook' );
	}

	try {
		return new Date( isoDate ).toLocaleDateString();
	} catch {
		return isoDate;
	}
}

export default function Overview() {
	const { runScan, fetchServices, fetchCookies } = useDispatch( STORE_KEY );
	const { settings, isScanRunning, scanError } = useSelect( ( select ) => {
		const store = select( STORE_KEY );
		return {
			settings: store.getSettings(),
			isScanRunning: store.isScanRunning(),
			scanError: store.getScanError(),
		};
	}, [] );

	const scanner = settings.scanner || {};
	const summary = scanner.summary || { cookies: 0, services: 0 };
	const policyLabel =
		{
			create: __( 'Publiée', 'nook' ),
			existing: __( 'Liée à une page existante', 'nook' ),
			none: __( 'Non configurée', 'nook' ),
		}[ ( settings.policy || {} ).mode ] || __( 'Non configurée', 'nook' );

	return (
		<div className="nook-dashboard-page">
			<Card>
				<CardBody>
					<div className="nook-dashboard__status">
						<span>{ __( 'Consentement', 'nook' ) }</span>
						<strong className="nook-dashboard__status-badge">
							{ __( 'Actif', 'nook' ) }
						</strong>
					</div>

					{ scanError && (
						<Notice status="error" isDismissible={ false }>
							{ scanError.message ||
								__( 'La dernière analyse a échoué.', 'nook' ) }
						</Notice>
					) }

					<div className="nook-dashboard__grid">
						<div className="nook-dashboard__section">
							<h3>{ __( 'Site', 'nook' ) }</h3>
							<p>
								{ sprintf(
									/* translators: %d: nombre de cookies détectés */
									__( '%d cookies détectés', 'nook' ),
									summary.cookies || 0
								) }
							</p>
							<p>
								{ sprintf(
									/* translators: %d: nombre de services actifs */
									__( '%d services actifs', 'nook' ),
									summary.services || 0
								) }
							</p>
							<p className="nook-step__meta">
								{ sprintf(
									/* translators: %s: date de la dernière analyse */
									__( 'Dernière analyse : %s', 'nook' ),
									formatDate( scanner.lastScanAt )
								) }
							</p>
						</div>

						<div className="nook-dashboard__section">
							<h3>{ __( 'Configuration', 'nook' ) }</h3>
							<p>{ __( 'Bannière : configurée', 'nook' ) }</p>
							<p>
								{ sprintf(
									/* translators: %s: état de la politique de cookies */
									__( 'Politique de cookies : %s', 'nook' ),
									policyLabel
								) }
							</p>
						</div>
					</div>

					<div className="nook-dashboard__actions">
						<Button
							variant="secondary"
							isBusy={ isScanRunning }
							disabled={ isScanRunning }
							onClick={ () =>
								runScan().then( () => {
									fetchServices();
									fetchCookies();
								} )
							}
						>
							{ __( 'Analyser à nouveau', 'nook' ) }
						</Button>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}
