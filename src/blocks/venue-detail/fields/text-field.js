/**
 * WordPress dependencies.
 */
import { RichText } from '@wordpress/block-editor';

/**
 * Text field component for venue details.
 *
 * Renders a generic editable text field (default field type).
 *
 * @since 1.0.0
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.value       - The current field value.
 * @param {Function} props.onChange    - Callback when value changes.
 * @param {string}   props.placeholder - Placeholder text.
 * @param {Function} props.onKeyDown   - Keyboard event handler.
 * @param {boolean}  props.disabled    - Whether the field is disabled.
 * @return {JSX.Element} The rendered text field.
 */
const TextField = ( { value, onChange, placeholder, onKeyDown, disabled } ) => {
	const baseClass = 'gatherpress-venue-detail__text';

	// Render non-editable placeholder when disabled.
	if ( disabled ) {
		return (
			<div className={ `${ baseClass } gatherpress-venue-detail--disabled` }>
				<span className="wp-block-gatherpress-venue-detail__placeholder">
					{ placeholder }
				</span>
			</div>
		);
	}

	return (
		<RichText
			tagName="div"
			className={ baseClass }
			value={ value }
			onChange={ onChange }
			placeholder={ placeholder }
			allowedFormats={ [] }
			onKeyDown={ onKeyDown }
		/>
	);
};

export default TextField;
