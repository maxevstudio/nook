/**
 * Orchestrateur de l'assistant de configuration (wizard) 7 étapes.
 *
 * La progression est sauvegardée côté serveur à chaque changement d'étape
 * (`wizard.currentStep`/`wizard.completedSteps` dans `nook_settings`), ce qui
 * permet de reprendre le wizard exactement où l'utilisateur l'a laissé après
 * un rafraîchissement de page (voir PROJECT.md « Assistant initial »).
 */
import { useState } from '@wordpress/element';
import { useDispatch, useSelect, select as wpSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../store';
import WizardNavigation from './wizard/WizardNavigation';
import SiteStep from './wizard/SiteStep';
import ScanStep from './wizard/ScanStep';
import ServicesStep from './wizard/ServicesStep';
import ConsentStep from './wizard/ConsentStep';
import DesignStep from './wizard/DesignStep';
import PolicyStep from './wizard/PolicyStep';
import FinishStep from './wizard/FinishStep';

const STEPS = [
	{ id: 'site', label: __( 'Site', 'nook' ) },
	{ id: 'analyse', label: __( 'Analyse', 'nook' ) },
	{ id: 'services', label: __( 'Services', 'nook' ) },
	{ id: 'consentement', label: __( 'Consentement', 'nook' ) },
	{ id: 'design', label: __( 'Design', 'nook' ) },
	{ id: 'politique', label: __( 'Politique de cookies', 'nook' ) },
	{ id: 'finalisation', label: __( 'Finalisation', 'nook' ) },
];

function getStepIndex( stepId ) {
	const index = STEPS.findIndex( ( step ) => step.id === stepId );
	return -1 === index ? 0 : index;
}

export default function Wizard( { onExit, onComplete, startStep } ) {
	const settings = useSelect(
		( select ) => select( STORE_KEY ).getSettings(),
		[]
	);
	const { saveSettings } = useDispatch( STORE_KEY );

	const initialStepId = ( () => {
		if ( startStep && STEPS.some( ( step ) => step.id === startStep ) ) {
			return startStep;
		}

		if (
			settings.wizard &&
			STEPS.some( ( step ) => step.id === settings.wizard.currentStep )
		) {
			return settings.wizard.currentStep;
		}

		return 'site';
	} )();

	const [ stepId, setStepId ] = useState( initialStepId );
	const [ completedSteps, setCompletedSteps ] = useState(
		( settings.wizard && settings.wizard.completedSteps ) || []
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState( null );

	// Brouillons locaux par étape : initialisés une seule fois à partir des
	// réglages déjà chargés (voir ARCHITECTURE.md §98 — l'état de formulaire
	// ponctuel reste local, seule la validation par étape est persistée).
	const [ site, setSite ] = useState( settings.site );
	const [ consent, setConsent ] = useState( settings.consent );
	const [ banner, setBanner ] = useState( settings.banner );
	const [ policy, setPolicy ] = useState( settings.policy );

	const currentIndex = getStepIndex( stepId );

	/**
	 * Sauvegarde un fragment de réglages et, le cas échéant, avance vers une
	 * autre étape une fois la sauvegarde confirmée.
	 *
	 * @param {Object} patch     Fragment de réglages à fusionner côté serveur.
	 * @param {string} [advance] Identifiant de l'étape à activer après sauvegarde.
	 */
	async function persist( patch, advance ) {
		setIsSaving( true );
		setSaveError( null );

		const nextCompleted =
			advance && ! completedSteps.includes( stepId )
				? [ ...completedSteps, stepId ]
				: completedSteps;

		await saveSettings( {
			...patch,
			wizard: {
				currentStep: advance || stepId,
				completedSteps: nextCompleted,
			},
		} );

		setIsSaving( false );

		const error = wpSelect( STORE_KEY ).getError();

		if ( error ) {
			setSaveError(
				error.message || __( 'La sauvegarde a échoué.', 'nook' )
			);
			return false;
		}

		if ( advance ) {
			setCompletedSteps( nextCompleted );
			setStepId( advance );
		}

		return true;
	}

	/**
	 * Crée la page de politique de cookies si nécessaire (mode « create »),
	 * en s'appuyant sur l'API REST native de WordPress plutôt qu'un
	 * endpoint Nook dédié — l'utilisateur courant est déjà un administrateur
	 * capable de créer des pages.
	 */
	async function ensurePolicyPage() {
		if ( 'create' !== policy.mode || policy.pageId ) {
			return policy;
		}

		try {
			const page = await apiFetch( {
				path: '/wp/v2/pages',
				method: 'POST',
				data: {
					title: policy.title,
					status: 'publish',
					content: '[nook_cookie_policy]',
				},
			} );

			const updated = { ...policy, pageId: page.id };
			setPolicy( updated );
			return updated;
		} catch ( error ) {
			setSaveError(
				error.message ||
					__(
						'La création de la page de politique de cookies a échoué.',
						'nook'
					)
			);
			return policy;
		}
	}

	/**
	 * Fragment de réglages à sauvegarder pour l'étape actuellement affichée
	 * (utilisé aussi bien en avançant qu'en changeant d'étape via la barre
	 * de progression, pour ne jamais perdre un brouillon en cours).
	 *
	 * @return {Object} Fragment de réglages à sauvegarder.
	 */
	function getCurrentStepPatch() {
		const patchByStep = {
			site: { site },
			analyse: {},
			services: {},
			consentement: { consent },
			design: { banner },
			politique: { policy },
		};

		return patchByStep[ stepId ] || {};
	}

	async function goNext() {
		const nextStep = STEPS[ currentIndex + 1 ];

		if ( ! nextStep ) {
			return;
		}

		if ( 'politique' === stepId ) {
			const resolvedPolicy = await ensurePolicyPage();
			await persist( { policy: resolvedPolicy }, nextStep.id );
			return;
		}

		await persist( getCurrentStepPatch(), nextStep.id );
	}

	async function goBack() {
		const previousStep = STEPS[ currentIndex - 1 ];

		if ( previousStep ) {
			await persist( getCurrentStepPatch(), previousStep.id );
		}
	}

	async function handleStepClick( targetStepId ) {
		if ( targetStepId === stepId ) {
			return;
		}

		if ( completedSteps.includes( targetStepId ) ) {
			await persist( getCurrentStepPatch(), targetStepId );
		}
	}

	async function handleExit() {
		await persist( getCurrentStepPatch() );
		onExit();
	}

	async function handleFinish() {
		const resolvedPolicy = await ensurePolicyPage();
		const success = await persist(
			{ policy: resolvedPolicy, setupCompleted: true },
			'finalisation'
		);

		if ( success ) {
			onComplete();
		}
	}

	return (
		<div className="nook-wizard">
			<Card>
				<CardHeader>
					<h2 className="nook-wizard__title">
						{ __( 'Assistant de configuration', 'nook' ) }
					</h2>
					<Button variant="tertiary" onClick={ handleExit }>
						{ __( 'Enregistrer et quitter', 'nook' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<WizardNavigation
						steps={ STEPS }
						currentStepId={ stepId }
						completedSteps={ completedSteps }
						onStepClick={ handleStepClick }
					/>

					{ saveError && (
						<Notice
							status="error"
							isDismissible={ false }
							className="nook-wizard__error"
						>
							{ saveError }
						</Notice>
					) }

					<div className="nook-wizard__content">
						{ 'site' === stepId && (
							<SiteStep
								value={ site }
								onChange={ ( patch ) =>
									setSite( { ...site, ...patch } )
								}
							/>
						) }
						{ 'analyse' === stepId && <ScanStep /> }
						{ 'services' === stepId && <ServicesStep /> }
						{ 'consentement' === stepId && (
							<ConsentStep
								value={ consent }
								onChange={ ( patch ) =>
									setConsent( { ...consent, ...patch } )
								}
							/>
						) }
						{ 'design' === stepId && (
							<DesignStep
								value={ banner }
								onChange={ ( patch ) =>
									setBanner( { ...banner, ...patch } )
								}
							/>
						) }
						{ 'politique' === stepId && (
							<PolicyStep
								value={ policy }
								onChange={ ( patch ) =>
									setPolicy( { ...policy, ...patch } )
								}
							/>
						) }
						{ 'finalisation' === stepId && (
							<FinishStep
								settings={ {
									...settings,
									banner,
									policy,
								} }
								onFinish={ handleFinish }
								isSaving={ isSaving }
							/>
						) }
					</div>
				</CardBody>
				{ 'finalisation' !== stepId && (
					<CardFooter className="nook-wizard__footer">
						<Button
							variant="tertiary"
							onClick={ goBack }
							disabled={ isSaving || 0 === currentIndex }
						>
							{ __( 'Précédent', 'nook' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ goNext }
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ __( 'Suivant', 'nook' ) }
						</Button>
					</CardFooter>
				) }
			</Card>
		</div>
	);
}
