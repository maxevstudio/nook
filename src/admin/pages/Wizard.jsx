/**
 * Coquille (shell) de l'assistant de configuration.
 *
 * L'implémentation complète des 7 étapes (Site, Analyse, Services,
 * Consentement, Design, Politique de cookies, Finalisation) est un chantier
 * à part entière — voir PROJECT.md « Assistant initial » et STATUS.md pour
 * l'avancement réel. Cette page pose uniquement la structure de navigation.
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STEPS = [
	{ id: 'site', label: __( 'Site', 'nook' ) },
	{ id: 'analyse', label: __( 'Analyse', 'nook' ) },
	{ id: 'services', label: __( 'Services', 'nook' ) },
	{ id: 'consentement', label: __( 'Consentement', 'nook' ) },
	{ id: 'design', label: __( 'Design', 'nook' ) },
	{ id: 'politique', label: __( 'Politique de cookies', 'nook' ) },
	{ id: 'finalisation', label: __( 'Finalisation', 'nook' ) },
];

export default function Wizard( { onExit } ) {
	return (
		<div className="nook-wizard">
			<Card>
				<CardHeader>
					<h2 className="nook-wizard__title">
						{ __( 'Assistant de configuration', 'nook' ) }
					</h2>
					<Button variant="tertiary" onClick={ onExit }>
						{ __( 'Quitter', 'nook' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<ol className="nook-wizard__steps">
						{ STEPS.map( ( step, index ) => (
							<li key={ step.id } className="nook-wizard__step">
								<span className="nook-wizard__step-number">
									{ index + 1 }
								</span>
								{ step.label }
							</li>
						) ) }
					</ol>
					<p>
						{ __(
							'Cet assistant est en cours de construction. Revenez bientôt pour analyser et configurer le consentement de votre site étape par étape.',
							'nook'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
