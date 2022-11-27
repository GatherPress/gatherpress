/**
 * WordPress dependencies.
 */
import { DateTimePicker } from '@wordpress/components';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalGetSettings } from '@wordpress/date';
import { withState } from '@wordpress/compose';

/**
 * Internal dependencies.
 */
import { updateDateTimeEnd, getDateTimeEnd } from './label';

export const DateTimeEnd = withState()( ( { setState } ) => {
	const settings = __experimentalGetSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase()
			.replace( /\\\\/g, '' )
			.split( '' )
			.reverse()
			.join( '' )
	);

	return (
		<DateTimePicker
			currentDate={ getDateTimeEnd() }
			onChange={ ( date ) => updateDateTimeEnd( date, setState ) }
			is12Hour={ is12HourTime }
		/>
	);
} );
