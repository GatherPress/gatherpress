/**
 * WordPress dependencies
 */
import { RichText } from '@wordpress/block-editor';

/**
 * Phone field component for venue details.
 *
 * Renders an editable phone field. When a value exists,
 * it renders as a clickable tel: link.
 *
 * @since 1.0.0
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.value       - The current field value.
 * @param {Function} props.onChange    - Callback when value changes.
 * @param {string}   props.placeholder - Placeholder text.
 * @param {Function} props.onKeyDown   - Keyboard event handler.
 * @param {boolean}  props.disabled    - Whether the field is disabled.
 * @return {JSX.Element} The rendered phone field.
 */
const PhoneField = ( {
	value,
	onChange,
	placeholder,
	onKeyDown,
	disabled,
} ) => {
	const baseClass = 'gatherpress-venue-detail__phone';

	// Render non-editable placeholder when disabled.
	if ( disabled ) {
		return (
			<span className={ `${ baseClass } gatherpress-venue-detail--disabled` }>
				<span className="wp-block-gatherpress-venue-detail__placeholder">
					{ placeholder }
				</span>
			</span>
		);
	}

	// Keep `tagName` stable across edits: flipping between `span` and `a` as the
	// value goes from empty to non-empty causes React to unmount/remount the
	// contenteditable element, which drops the cursor after the first keystroke.
	// The frontend in render.php still emits the proper `tel:` anchor.
	return (
		<RichText
			tagName="a"
			href={ value ? `tel:${ value }` : '#' }
			className={ baseClass }
			value={ value }
			onChange={ onChange }
			placeholder={ placeholder }
			allowedFormats={ [] }
			onKeyDown={ onKeyDown }
			onClick={ ( e ) => e.preventDefault() }
		/>
	);
};

export default PhoneField;
