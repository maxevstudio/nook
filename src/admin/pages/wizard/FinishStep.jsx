/**
 * Étape 7 — Finalisation : récapitulatif et activation de Nook (voir
 * PROJECT.md §36).
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const POLICY_LABELS = {
	create: __( 'Une nouvelle page va être créée', 'nook' ),
	existing: __( 'Liée à une page existante', 'nook' ),
	none: __( 'Non configurée pour le moment', 'nook' ),
};

export default function FinishStep( { settings, onFinish, isSaving } ) {
	const summary = ( settings.scanner && settings.scanner.summary ) || {
		cookies: 0,
		services: 0,
	};
	const bannerConfigured = Boolean(
		settings.banner && settings.banner.title
	);
	const policyLabel = POLICY_LABELS[ settings.policy.mode ] || '';

	return (
		<div className="nook-step nook-step--finish">
			<h3>{ __( 'Récapitulatif', 'nook' ) }</h3>
			<ul className="nook-finish-summary">
				<li>
					{ sprintf(
						/* translators: %d: nombre de cookies détectés */
						__( '%d cookies détectés', 'nook' ),
						summary.cookies || 0
					) }
				</li>
				<li>
					{ sprintf(
						/* translators: %d: nombre de services détectés */
						__( '%d services détectés', 'nook' ),
						summary.services || 0
					) }
				</li>
				<li>
					{ bannerConfigured
						? __( 'Bannière configurée', 'nook' )
						: __( 'Bannière non configurée', 'nook' ) }
				</li>
				<li>
					{ sprintf(
						/* translators: %s: état de la politique de cookies */
						__( 'Politique de cookies : %s', 'nook' ),
						policyLabel
					) }
				</li>
			</ul>
			<p>
				{ __(
					'En activant Nook, la bannière de consentement sera affichée sur votre site pour vos visiteurs. Vous pourrez modifier cette configuration à tout moment depuis le tableau de bord.',
					'nook'
				) }
			</p>
			<Button
				variant="primary"
				onClick={ onFinish }
				isBusy={ isSaving }
				disabled={ isSaving }
			>
				{ __( 'Activer Nook', 'nook' ) }
			</Button>
		</div>
	);
}
