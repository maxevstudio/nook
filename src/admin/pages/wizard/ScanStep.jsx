/**
 * Étape 2 — Analyse : lance le scanner serveur et présente les résultats
 * bruts (voir PROJECT.md §11-16). Le scan est synchrone côté serveur : on
 * affiche un état « en cours » honnête plutôt qu'une fausse progression
 * précise (voir PROJECT.md §100).
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { STORE_KEY } from '../../store';

export default function ScanStep() {
	const { runScan, fetchServices } = useDispatch( STORE_KEY );
	const { isRunning, error, settings, lastResults } = useSelect(
		( select ) => {
			const store = select( STORE_KEY );
			return {
				isRunning: store.isScanRunning(),
				error: store.getScanError(),
				settings: store.getSettings(),
				lastResults: store.getLastScanResults(),
			};
		},
		[]
	);

	const scanner = ( settings && settings.scanner ) || {};
	const hasScanned = Boolean( scanner.lastScanAt );
	const summary = scanner.summary || { cookies: 0, services: 0, toReview: 0 };
	const partialErrors =
		( lastResults && lastResults.results && lastResults.results.errors ) ||
		[];

	return (
		<div className="nook-step nook-step--scan">
			<p className="nook-step__intro">
				{ __(
					'Nook peut analyser votre site pour identifier les cookies, scripts, domaines et contenus tiers utilisés. L’analyse porte sur un nombre limité de pages et reste entièrement locale à votre installation WordPress : aucune donnée n’est envoyée à un service externe.',
					'nook'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error.message || __( 'L’analyse a échoué.', 'nook' ) }
				</Notice>
			) }

			{ isRunning && (
				<div className="nook-step__loading">
					<Spinner />
					<span>
						{ __(
							'Analyse en cours… cela peut prendre jusqu’à une minute.',
							'nook'
						) }
					</span>
				</div>
			) }

			{ ! isRunning && hasScanned && (
				<div className="nook-scan-summary">
					<h3>{ __( 'Analyse terminée', 'nook' ) }</h3>
					<ul>
						<li>
							{ sprintf(
								/* translators: %d: nombre de cookies détectés */
								__( '%d cookies détectés', 'nook' ),
								summary.cookies
							) }
						</li>
						<li>
							{ sprintf(
								/* translators: %d: nombre de services détectés */
								__( '%d services détectés', 'nook' ),
								summary.services
							) }
						</li>
						<li>
							{ sprintf(
								/* translators: %d: nombre d'éléments à vérifier */
								__( '%d éléments à vérifier', 'nook' ),
								summary.toReview
							) }
						</li>
					</ul>
					{ scanner.urls && scanner.urls.length > 0 && (
						<p className="nook-step__meta">
							{ sprintf(
								/* translators: %d: nombre de pages analysées */
								__( '%d pages analysées.', 'nook' ),
								scanner.urls.length
							) }
						</p>
					) }
				</div>
			) }

			{ partialErrors.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: nombre de pages qui n'ont pas pu être analysées */
						__(
							'%d page(s) n’ont pas pu être analysées (erreur réseau ou HTTP). Les autres pages ont bien été traitées.',
							'nook'
						),
						partialErrors.length
					) }
				</Notice>
			) }

			<Button
				variant={ hasScanned ? 'secondary' : 'primary' }
				onClick={ () => runScan().then( () => fetchServices() ) }
				isBusy={ isRunning }
				disabled={ isRunning }
			>
				{ hasScanned
					? __( 'Analyser à nouveau', 'nook' )
					: __( 'Analyser le site', 'nook' ) }
			</Button>
		</div>
	);
}
