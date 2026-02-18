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
 * @return {JSX.Element} The rendered text field.
 */
const TextField = ( { value, onChange, placeholder, onKeyDown } ) => {
	return (
		<RichText
			tagName="div"
			className="gatherpress-venue-detail__text"
			value={ value }
			onChange={ onChange }
			placeholder={ placeholder }
			allowedFormats={ [] }
			onKeyDown={ onKeyDown }
		/>
	);
};

export default TextField;
