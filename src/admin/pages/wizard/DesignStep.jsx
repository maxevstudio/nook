/**
 * Étape 5 — Design : apparence de la bannière avec aperçu en temps réel
 * (voir PROJECT.md §30-32). L'aperçu est une simple maquette statique,
 * visuellement distincte de la vraie bannière du site, et n'enregistre
 * jamais de consentement réel.
 */
import {
	Notice,
	RangeControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ColorField from '../../components/ColorField';

const POSITIONS = [
	{ label: __( 'Bas de l’écran', 'nook' ), value: 'bottom' },
	{ label: __( 'Haut de l’écran', 'nook' ), value: 'top' },
];

const BUTTON_STYLES = [
	{ label: __( 'Plein', 'nook' ), value: 'solid' },
	{ label: __( 'Contour', 'nook' ), value: 'outline' },
];

const WIDTHS = [
	{ label: __( 'Compacte (coin de l’écran)', 'nook' ), value: 'compact' },
	{ label: __( 'Pleine largeur', 'nook' ), value: 'full' },
	{ label: __( 'Encadrée', 'nook' ), value: 'contained' },
];

export default function DesignStep( { value, onChange } ) {
	function updateButton( key, label ) {
		onChange( { buttons: { ...value.buttons, [ key ]: label } } );
	}

	return (
		<div className="nook-step nook-step--design">
			<div className="nook-design-editor">
				<div className="nook-design-editor__form">
					<TextControl
						label={ __( 'Titre de la bannière', 'nook' ) }
						value={ value.title }
						onChange={ ( title ) => onChange( { title } ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Texte de la bannière', 'nook' ) }
						value={ value.text }
						onChange={ ( text ) => onChange( { text } ) }
						rows={ 3 }
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={ __( 'Bouton « Tout accepter »', 'nook' ) }
						value={ value.buttons.acceptAll }
						onChange={ ( label ) =>
							updateButton( 'acceptAll', label )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Bouton « Tout refuser »', 'nook' ) }
						value={ value.buttons.rejectAll }
						onChange={ ( label ) =>
							updateButton( 'rejectAll', label )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Bouton « Personnaliser »', 'nook' ) }
						value={ value.buttons.customize }
						onChange={ ( label ) =>
							updateButton( 'customize', label )
						}
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={ __( 'Position', 'nook' ) }
						value={ value.position }
						options={ POSITIONS }
						onChange={ ( position ) => onChange( { position } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Style des boutons', 'nook' ) }
						value={ value.buttonStyle }
						options={ BUTTON_STYLES }
						onChange={ ( buttonStyle ) =>
							onChange( { buttonStyle } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Largeur', 'nook' ) }
						value={ value.width }
						options={ WIDTHS }
						onChange={ ( width ) => onChange( { width } ) }
						__nextHasNoMarginBottom
					/>

					<div className="nook-design-editor__colors">
						<ColorField
							label={ __( 'Fond', 'nook' ) }
							value={ value.backgroundColor }
							onChange={ ( backgroundColor ) =>
								onChange( { backgroundColor } )
							}
						/>
						<ColorField
							label={ __( 'Texte', 'nook' ) }
							value={ value.textColor }
							onChange={ ( textColor ) =>
								onChange( { textColor } )
							}
						/>
						<ColorField
							label={ __( 'Accent (bouton principal)', 'nook' ) }
							value={ value.accentColor }
							onChange={ ( accentColor ) =>
								onChange( { accentColor } )
							}
						/>
					</div>

					<RangeControl
						label={ __( 'Rayon des bordures', 'nook' ) }
						value={ value.radius }
						min={ 0 }
						max={ 24 }
						onChange={ ( radius ) => onChange( { radius } ) }
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={ __(
							'Assombrir le reste de la page (overlay)',
							'nook'
						) }
						checked={ Boolean( value.overlay ) }
						onChange={ ( overlay ) => onChange( { overlay } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Pastille « Préférences cookies » en bas à gauche',
							'nook'
						) }
						help={ __(
							'Affichée une fois le choix fait, elle permet au visiteur de modifier son consentement à tout moment.',
							'nook'
						) }
						checked={ false !== value.floatingButton }
						onChange={ ( floatingButton ) =>
							onChange( { floatingButton } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Lien « Gérer mes cookies » en pied de page',
							'nook'
						) }
						checked={ Boolean( value.footerLink ) }
						onChange={ ( footerLink ) =>
							onChange( { footerLink } )
						}
						__nextHasNoMarginBottom
					/>
					{ false === value.floatingButton && ! value.footerLink && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Sans pastille ni lien de pied de page, ajoutez le shortcode [nook_cookie_settings] quelque part sur le site : le visiteur doit toujours pouvoir modifier son choix.',
								'nook'
							) }
						</Notice>
					) }
				</div>

				<div className="nook-design-editor__preview">
					<p className="nook-design-editor__preview-label">
						{ __( 'Aperçu (non fonctionnel)', 'nook' ) }
					</p>
					<div className="nook-preview-frame">
						{ value.overlay && (
							<div className="nook-preview-frame__overlay" />
						) }
						<div
							className={ `nook-preview-banner nook-preview-banner--${ value.position } nook-preview-banner--${ value.width }` }
							style={ {
								backgroundColor: value.backgroundColor,
								color: value.textColor,
								borderRadius: `${ value.radius }px`,
							} }
						>
							<strong>{ value.title }</strong>
							<p>{ value.text }</p>
							<div className="nook-preview-banner__buttons">
								<span
									className={ `nook-preview-button nook-preview-button--${ value.buttonStyle }` }
									style={
										'solid' === value.buttonStyle
											? {
													backgroundColor:
														value.accentColor,
													color: '#ffffff',
												}
											: {
													borderColor:
														value.accentColor,
													color: value.accentColor,
												}
									}
								>
									{ value.buttons.acceptAll }
								</span>
								<span className="nook-preview-button nook-preview-button--outline">
									{ value.buttons.rejectAll }
								</span>
								<span className="nook-preview-button nook-preview-button--text">
									{ value.buttons.customize }
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
