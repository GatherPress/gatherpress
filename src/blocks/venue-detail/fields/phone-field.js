/**
 * WordPress dependencies.
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
 * @return {JSX.Element} The rendered phone field.
 */
const PhoneField = ( { value, onChange, placeholder, onKeyDown } ) => {
	const commonProps = {
		value,
		onChange,
		placeholder,
		allowedFormats: [],
		onKeyDown,
		className: 'gatherpress-venue-detail__phone',
	};

	// Render as a link with tel: href when value exists.
	if ( value ) {
		return (
			<RichText
				{ ...commonProps }
				tagName="a"
				href={ `tel:${ value }` }
				onClick={ ( e ) => e.preventDefault() }
			/>
		);
	}

	// Render as a span when empty.
	return <RichText { ...commonProps } tagName="span" />;
};

export default PhoneField;
