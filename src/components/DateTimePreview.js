/**
 * WordPress dependencies.
 */
import { format } from '@wordpress/date';
import { useState } from '@wordpress/element';

/**
 * DateTimePreview component for GatherPress.
 *
 * This component renders a preview of the formatted date and time based on the specified format.
 * It listens for the 'input' event on the input field with the specified name and updates
 * the state with the new date and time format. The formatted preview is displayed accordingly.
 *
 * @since 1.0.0
 *
 * @param {Object} props             - Component props.
 * @param {Object} props.attrs       - Component attributes.
 * @param {string} props.attrs.name  - The name of the input field.
 * @param {string} props.attrs.value - The initial value of the input field (date and time format).
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimePreview = ( props ) => {
	const { name, value } = props.attrs;
	const [ dateTimeFormat, setDateTimeFormat ] = useState( value );

	const input = document.querySelector( `[name="${ name }"]` );

	input.addEventListener(
		'input',
		( e ) => {
			setDateTimeFormat( e.target.value );
		},
		{ once: true },
	);

	return <>{ dateTimeFormat && format( dateTimeFormat ) }</>;
};

export default DateTimePreview;
