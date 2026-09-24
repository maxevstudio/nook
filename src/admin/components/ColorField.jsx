/**
 * Champ de sélection de couleur compact (pastille + popover ColorPicker),
 * construit uniquement à partir de composants `@wordpress/components`
 * stables (voir AGENTS.md règle 2/3 — pas d'API expérimentale).
 */
import {
	Button,
	ColorIndicator,
	ColorPicker,
	Dropdown,
} from '@wordpress/components';

export default function ColorField( { label, value, onChange } ) {
	return (
		<div className="nook-color-field">
			<span className="nook-color-field__label">{ label }</span>
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="nook-color-field__toggle"
						onClick={ onToggle }
						aria-expanded={ isOpen }
					>
						<ColorIndicator colorValue={ value } />
						<span>{ value }</span>
					</Button>
				) }
				renderContent={ () => (
					<ColorPicker
						color={ value }
						onChange={ onChange }
						enableAlpha={ false }
					/>
				) }
			/>
		</div>
	);
}
