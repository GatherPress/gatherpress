/**
 * WordPress dependencies.
 */
import { RichText } from '@wordpress/block-editor';

/**
 * Address field component for venue details.
 *
 * Renders an editable address field using the semantic <address> HTML element.
 *
 * @since 1.0.0
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.value       - The current field value.
 * @param {Function} props.onChange    - Callback when value changes.
 * @param {string}   props.placeholder - Placeholder text.
 * @param {Function} props.onKeyDown   - Keyboard event handler.
 * @param {boolean}  props.disabled    - Whether the field is disabled.
 * @return {JSX.Element} The rendered address field.
 */
const AddressField = ( {
	value,
	onChange,
	placeholder,
	onKeyDown,
	disabled,
} ) => {
	const baseClass = 'gatherpress-venue-detail__address';

	// Render non-editable placeholder when disabled.
	if ( disabled ) {
		return (
			<address className={ baseClass } style={ { display: 'inline' } }>
				<span className="wp-block-gatherpress-venue-detail__placeholder">
					{ placeholder }
				</span>
			</address>
		);
	}

	return (
		<RichText
			tagName="address"
			className={ baseClass }
			style={ { display: 'inline' } }
			value={ value }
			onChange={ onChange }
			placeholder={ placeholder }
			allowedFormats={ [] }
			onKeyDown={ onKeyDown }
		/>
	);
};

export default AddressField;
