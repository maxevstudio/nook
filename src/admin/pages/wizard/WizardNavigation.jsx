/**
 * Barre de progression du wizard : affiche les 7 étapes, marque celles déjà
 * complétées et permet de revenir en arrière (voir PROJECT.md « Assistant
 * initial »). On n'autorise jamais à sauter en avant une étape non encore
 * complétée : seule la navigation vers l'étape courante ou une étape déjà
 * validée est possible.
 */
import { __ } from '@wordpress/i18n';
import { check } from '@wordpress/icons';
import { Icon } from '@wordpress/components';

export default function WizardNavigation( {
	steps,
	currentStepId,
	completedSteps,
	onStepClick,
} ) {
	return (
		<ol
			className="nook-wizard-nav"
			aria-label={ __( 'Étapes de la configuration', 'nook' ) }
		>
			{ steps.map( ( step, index ) => {
				const isCompleted = completedSteps.includes( step.id );
				const isCurrent = step.id === currentStepId;
				const isClickable = isCompleted || isCurrent;

				const className = [
					'nook-wizard-nav__step',
					isCurrent ? 'is-current' : '',
					isCompleted ? 'is-completed' : '',
				]
					.filter( Boolean )
					.join( ' ' );

				return (
					<li key={ step.id } className={ className }>
						<button
							type="button"
							className="nook-wizard-nav__button"
							disabled={ ! isClickable }
							aria-current={ isCurrent ? 'step' : undefined }
							onClick={ () =>
								isClickable && onStepClick( step.id )
							}
						>
							<span
								className="nook-wizard-nav__marker"
								aria-hidden="true"
							>
								{ isCompleted ? (
									<Icon icon={ check } size={ 16 } />
								) : (
									index + 1
								) }
							</span>
							<span className="nook-wizard-nav__label">
								{ step.label }
							</span>
						</button>
					</li>
				);
			} ) }
		</ol>
	);
}
