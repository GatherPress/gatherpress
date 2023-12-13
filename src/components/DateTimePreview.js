/**
 * WordPress dependencies.
 */
import { format } from '@wordpress/date';
import { useState } from '@wordpress/element';

const DateTimePreview = (props) => {
	const { name, value } = props.attrs;
	const [dateTimeFormat, setDateTimeFormat] = useState(value);

	const input = document.querySelector(`[name="${name}"]`);

	input.addEventListener(
		'input',
		(e) => {
			setDateTimeFormat(e.target.value);
		},
		{ once: true }
	);

	return <>{dateTimeFormat && format(dateTimeFormat)}</>;
};

export default DateTimePreview;
