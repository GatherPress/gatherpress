/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { createRoot } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import { getFromGlobal, setToGlobal } from './globals';
import { enableSave } from './editor';
import DateTimePreview from '../components/DateTimePreview';

/**
 * Database-compatible date and time format string for storage.
 *
 * This format is designed to represent date and time in the format
 * "YYYY-MM-DD HH:mm:ss" for compatibility with database storage.
 *
 * @since 1.0.0
 *
 * @type {string}
 */
export const dateTimeDatabaseFormat = 'YYYY-MM-DD HH:mm:ss';

/**
 * The default start date and time for an event.
 * It is set to the current date and time plus one day at 18:00:00 in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default start date and time in the application's timezone.
 */
export const defaultDateTimeStart = moment
	.tz( getTimezone() )
	.add( 1, 'day' )
	.set( 'hour', 18 )
	.set( 'minute', 0 )
	.set( 'second', 0 )
	.format( dateTimeDatabaseFormat );

/**
 * The default end date and time for an event.
 * It is calculated based on the default start date and time plus two hours in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default end date and time in the application's timezone.
 */
export const defaultDateTimeEnd = moment
	.tz( defaultDateTimeStart, getTimezone() )
	.add( 2, 'hours' )
	.format( dateTimeDatabaseFormat );

/**
 * Predefined duration options for event scheduling.
 *
 * This array contains a list of duration options in hours that can be selected
 * for an event. Each option includes a label for display and a corresponding
 * value representing the duration in hours. The last option allows the user
 * to set a custom end time by selecting `false`.
 *
 * @since 1.0.0
 *
 * @property {string}         label - The human-readable label for the duration option.
 * @property {number|boolean} value - The value representing the duration in hours, or `false` if a custom end time is to be set.
 */

export function durationOptions() {
	const options = [
		{
			label: __( '1 hour', 'gatherpress' ),
			value: 1,
		},
		{
			label: __( '1.5 hours', 'gatherpress' ),
			value: 1.5,
		},
		{
			label: __( '2 hours', 'gatherpress' ),
			value: 2,
		},
		{
			label: __( '3 hours', 'gatherpress' ),
			value: 3,
		},
		{
			label: __( 'Set an end time…', 'gatherpress' ),
			value: false,
		},
	];

	return applyFilters( 'gatherpress.durationOptions', options );
}

/**
 * Calculates an offset in hours from the start date and time of an event.
 *
 * This function retrieves the event's start date and time, applies the provided
 * offset in hours, and returns the result formatted for database storage.
 *
 * @since 1.0.0
 *
 * @param {number} hours - The number of hours to offset from the event's start date and time.
 *
 * @return {string} The adjusted date and time formatted in a database-compatible format.
 */
export function dateTimeOffset( hours ) {
	return moment
		.tz( getDateTimeStart(), getTimezone() )
		.add( hours, 'hours' )
		.format( dateTimeDatabaseFormat );
}

/**
 * Retrieves the duration offset based on the end time of the event.
 *
 * This function checks the available duration options and compares
 * the offset value with the calculated end time of the event. If a
 * matching offset is found, it returns the corresponding value. If
 * no match is found, it returns false.
 *
 * @since 1.0.0
 *
 * @return {number|boolean} The matching duration value or false if no match is found.
 */
export function getDateTimeOffset() {
	return (
		durationOptions().find(
			( option ) => dateTimeOffset( option.value ) === getDateTimeEnd(),
		)?.value || false
	);
}

/**
 * Get the combined date and time format for event labels.
 *
 * This function retrieves the date and time formats from global settings
 * and combines them to create a formatted label for event start and end times.
 *
 * @since 1.0.0
 *
 * @return {string} The combined date and time format for event labels.
 */
export function dateTimeLabelFormat() {
	const dateFormat = convertPHPToMomentFormat(
		getFromGlobal( 'settings.dateFormat' ),
	);
	const timeFormat = convertPHPToMomentFormat(
		getFromGlobal( 'settings.timeFormat' ),
	);

	return dateFormat + ' ' + timeFormat;
}

/**
 * Retrieves the timezone for the application based on the provided timezone or the global setting.
 * If the provided timezone is invalid, the default timezone is set to 'GMT'.
 *
 * @since 1.0.0
 *
 * @param {string} timezone - The timezone to be used, defaults to the global setting 'event_datetime.timezone'.
 *
 * @return {string} The retrieved timezone, or 'GMT' if the provided timezone is invalid.
 */
export function getTimezone(
	timezone = getFromGlobal( 'eventDetails.dateTime.timezone' ),
) {
	if ( !! moment.tz.zone( timezone ) ) {
		return timezone;
	}

	return __( 'GMT', 'gatherpress' );
}

/**
 * Retrieves the UTC offset for a given timezone.
 * If the timezone is not set to 'GMT', an empty string is returned.
 *
 * @since 1.0.0
 *
 * @param {string} timezone - The timezone for which to retrieve the UTC offset.
 *
 * @return {string} UTC offset without the colon if the timezone is set to 'GMT', otherwise an empty string.
 */
export function getUtcOffset( timezone ) {
	timezone = getTimezone( timezone );

	if ( __( 'GMT', 'gatherpress' ) !== timezone ) {
		return '';
	}

	const offset = getFromGlobal( 'eventDetails.dateTime.timezone' );

	return maybeConvertUtcOffsetForDisplay( offset );
}

/**
 * Converts a UTC offset string to a format suitable for display,
 * removing the colon (:) between hours and minutes.
 *
 * @since 1.0.0
 *
 * @param {string} offset - The UTC offset string to be converted.
 *
 * @return {string} Converted UTC offset without the colon, suitable for display.
 */
export function maybeConvertUtcOffsetForDisplay( offset = '' ) {
	return offset.replace( ':', '' );
}

/**
 * Converts a UTC offset string to a standardized format suitable for database storage.
 * The function accepts offsets in the form of 'UTC+HH:mm', 'UTC-HH:mm', 'UTC+HH', or 'UTC-HH'.
 * The resulting format is '+HH:mm' or '-HH:mm'.
 *
 * @since 1.0.0
 *
 * @param {string} offset - The UTC offset string to be converted.
 *
 * @return {string} Converted UTC offset in the format '+HH:mm' or '-HH:mm'.
 */
export function maybeConvertUtcOffsetForDatabase( offset = '' ) {
	// Regex: https://regex101.com/r/9bMgJd/3.
	const pattern = /^UTC([+-])(\d+)(\.\d+)?$/;
	const sign = offset.replace( pattern, '$1' );

	if ( sign !== offset ) {
		const hour = offset.replace( pattern, '$2' ).padStart( 2, '0' );
		let minute = offset.replace( pattern, '$3' );

		if ( '' === minute ) {
			minute = ':00';
		}

		minute = minute
			.replace( '.25', ':15' )
			.replace( '.5', ':30' )
			.replace( '.75', ':45' );

		return sign + hour + minute;
	}

	return offset;
}

/**
 * Converts a UTC offset string to a format suitable for dropdown selection,
 * specifically in the format '+HH:mm' or '-HH:mm'.
 *
 * @since 1.0.0
 *
 * @param {string} offset - The UTC offset string to be converted.
 *
 * @return {string} Converted UTC offset in the format '+HH:mm' or '-HH:mm'.
 */
export function maybeConvertUtcOffsetForSelect( offset = '' ) {
	// Regex: https://regex101.com/r/nOXCPo/2.
	const pattern = /^([+-])(\d{2}):(00|15|30|45)$/;
	const sign = offset.replace( pattern, '$1' );

	if ( sign !== offset ) {
		const hour = parseInt( offset.replace( pattern, '$2' ) ).toString();
		const minute = offset
			.replace( pattern, '$3' )
			.replace( '00', '' )
			.replace( '15', '.25' )
			.replace( '30', '.5' )
			.replace( '45', '.75' );

		return 'UTC' + sign + hour + minute;
	}

	return offset;
}

/**
 * Retrieves the start date and time for an event, formatted based on the plugin's timezone.
 * If the start date and time is not set, it defaults to a predefined value.
 * The formatted datetime is then stored in the global settings for future access.
 *
 * @since 1.0.0
 *
 * @return {string} The formatted start date and time for the event.
 */
export function getDateTimeStart() {
	let dateTime = getFromGlobal( 'eventDetails.dateTime.datetime_start' );

	dateTime =
		'' !== dateTime
			? moment.tz( dateTime, getTimezone() ).format( dateTimeDatabaseFormat )
			: defaultDateTimeStart;

	setToGlobal( 'eventDetails.dateTime.datetime_start', dateTime );

	return dateTime;
}

/**
 * Retrieves the end date and time for an event, formatted based on the plugin's timezone.
 * If the end date and time is not set, it defaults to a predefined value.
 * The formatted datetime is then stored in the global settings for future access.
 *
 * @since 1.0.0
 *
 * @return {string} The formatted end date and time for the event.
 */
export function getDateTimeEnd() {
	let dateTime = getFromGlobal( 'eventDetails.dateTime.datetime_end' );

	dateTime =
		'' !== dateTime
			? moment.tz( dateTime, getTimezone() ).format( dateTimeDatabaseFormat )
			: defaultDateTimeEnd;

	setToGlobal( 'eventDetails.dateTime.datetime_end', dateTime );

	return dateTime;
}

/**
 * Updates the start date and time for an event, performs validation, and triggers the save functionality.
 *
 * This function sets the new start date and time of the event, validates the input
 * to ensure it meets the required criteria, and updates the global state. It also
 * triggers a save action if the `enableSave` function is available. If a `setDateTimeStart`
 * callback is provided, it is invoked with the new date.
 *
 * @since 1.0.0
 *
 * @param {string}        date             - The new start date and time to be set in a valid format.
 * @param {Function|null} setDateTimeStart - Optional callback function to update the state or perform additional actions with the new start date.
 * @param {Function|null} setDateTimeEnd   - Optional callback function to update the end date, if validation requires an update.
 *
 * @return {void}
 */
export function updateDateTimeStart(
	date,
	setDateTimeStart = null,
	setDateTimeEnd = null,
) {
	// Store the current duration before updating the start time.
	const currentDuration = getDateTimeOffset();

	setToGlobal( 'eventDetails.dateTime.datetime_start', date );

	// If in relative mode (duration is numeric), always update the end time to maintain the offset.
	if ( 'number' === typeof currentDuration ) {
		const dateTimeEnd = moment
			.tz( date, getTimezone() )
			.add( currentDuration, 'hours' )
			.format( dateTimeDatabaseFormat );

		updateDateTimeEnd( dateTimeEnd, setDateTimeEnd );
	} else {
		// Otherwise, only validate to ensure end is after start.
		validateDateTimeStart( date, setDateTimeEnd, currentDuration );
	}

	if ( 'function' === typeof setDateTimeStart ) {
		setDateTimeStart( date );
	}

	enableSave();
}

/**
 * Updates the end date and time of the event and triggers necessary actions.
 *
 * This function sets the end date and time of the event to the specified value,
 * validates the input, and triggers additional actions such as updating the UI and
 * enabling save functionality. The `setDateTimeEnd` callback can be used to update
 * the UI with the new end date and time, if provided. Optionally, `setDateTimeStart`
 * can be used for validation against the start date and time.
 *
 * @since 1.0.0
 *
 * @param {string}        date             - The new end date and time in a valid format.
 * @param {Function|null} setDateTimeEnd   - Optional callback to update the UI with the new end date and time.
 * @param {Function|null} setDateTimeStart - Optional callback for validating the end date against the start date.
 *
 * @return {void}
 */
export function updateDateTimeEnd(
	date,
	setDateTimeEnd = null,
	setDateTimeStart = null,
) {
	validateDateTimeEnd( date, setDateTimeStart );

	setToGlobal( 'eventDetails.dateTime.datetime_end', date );

	if ( null !== setDateTimeEnd ) {
		setDateTimeEnd( date );
	}

	enableSave();
}

/**
 * Validates the start date and time of the event and performs necessary adjustments if needed.
 *
 * This function compares the provided start date and time with the current end date
 * and time of the event. If the start date is greater than or equal to the end date,
 * it adjusts the end date. If there's an active duration (relative mode), it maintains
 * that duration offset. Otherwise, it defaults to a two-hour duration.
 * If `setDateTimeEnd` is provided, it updates the end date accordingly.
 *
 * @since 1.0.0
 *
 * @param {string}        dateTimeStart   - The start date and time in a valid format.
 * @param {Function|null} setDateTimeEnd  - Optional callback to update the end date and time.
 * @param {number|false}  currentDuration - The current duration in hours (numeric for relative mode, false for absolute mode).
 *
 * @return {void}
 */
export function validateDateTimeStart( dateTimeStart, setDateTimeEnd = null, currentDuration = null ) {
	const dateTimeEndNumeric = moment
		.tz( getFromGlobal( 'eventDetails.dateTime.datetime_end' ), getTimezone() )
		.valueOf();
	const dateTimeStartNumeric = moment
		.tz( dateTimeStart, getTimezone() )
		.valueOf();

	if ( dateTimeStartNumeric >= dateTimeEndNumeric ) {
		// Use the passed duration if available, otherwise check current offset.
		// Only use duration if it's numeric (relative mode), not if it's false (absolute mode).
		const duration = null !== currentDuration ? currentDuration : getDateTimeOffset();
		const hoursToAdd = ( false !== duration && 'number' === typeof duration ) ? duration : 2;

		const dateTimeEnd = moment
			.tz( dateTimeStartNumeric, getTimezone() )
			.add( hoursToAdd, 'hours' )
			.format( dateTimeDatabaseFormat );

		updateDateTimeEnd( dateTimeEnd, setDateTimeEnd );
	}
}

/**
 * Validates the end date and time of the event and performs necessary adjustments if needed.
 *
 * This function compares the provided end date and time with the current start date
 * and time of the event. If the end date is less than or equal to the start date,
 * it adjusts the start date to ensure a minimum two-hour duration from the end date.
 * If `setDateTimeStart` is provided, it updates the start date accordingly.
 *
 * @since 1.0.0
 *
 * @param {string}        dateTimeEnd      - The end date and time in a valid format.
 * @param {Function|null} setDateTimeStart - Optional callback to update the start date and time.
 *
 * @return {void}
 */
export function validateDateTimeEnd( dateTimeEnd, setDateTimeStart = null ) {
	const dateTimeStartNumeric = moment
		.tz(
			getFromGlobal( 'eventDetails.dateTime.datetime_start' ),
			getTimezone(),
		)
		.valueOf();
	const dateTimeEndNumeric = moment.tz( dateTimeEnd, getTimezone() ).valueOf();

	if ( dateTimeEndNumeric <= dateTimeStartNumeric ) {
		const dateTimeStart = moment
			.tz( dateTimeEndNumeric, getTimezone() )
			.subtract( 2, 'hours' )
			.format( dateTimeDatabaseFormat );
		updateDateTimeStart( dateTimeStart, setDateTimeStart );
	}
}

/**
 * Convert PHP date format to Moment.js date format.
 *
 * This function converts a PHP date format string to its equivalent Moment.js date format.
 * It uses a mapping of PHP format characters to Moment.js format characters.
 *
 * @see https://gist.github.com/neilrackett/7881b5bef4cb4ae63af5c3a6a244cffa
 *
 * @since 1.0.0
 *
 * @param {string} format - The PHP date format to be converted.
 * @return {string} The equivalent Moment.js date format.
 */
export function convertPHPToMomentFormat( format ) {
	const replacements = {
		d: 'DD',
		D: 'ddd',
		j: 'D',
		l: 'dddd',
		N: 'E',
		S: 'o',
		w: 'e',
		z: 'DDD',
		W: 'W',
		F: 'MMMM',
		m: 'MM',
		M: 'MMM',
		n: 'M',
		t: '', // no equivalent
		L: '', // no equivalent
		o: 'YYYY',
		Y: 'YYYY',
		y: 'YY',
		a: 'a',
		A: 'A',
		B: '', // no equivalent
		g: 'h',
		G: 'H',
		h: 'hh',
		H: 'HH',
		i: 'mm',
		s: 'ss',
		u: 'SSS',
		e: 'zz', // deprecated since Moment.js 1.6.0
		I: '', // no equivalent
		O: '', // no equivalent
		P: '', // no equivalent
		T: '', // no equivalent
		Z: '', // no equivalent
		c: '', // no equivalent
		r: '', // no equivalent
		U: 'X',
	};
	return String( format )
		.split( '' )
		.map( ( chr, index, elements ) => {
			// Allow the format string to contain escaped chars, like ES or DE needs
			const last = elements[ index - 1 ];
			if ( chr in replacements && '\\' !== last ) {
				return replacements[ chr ];
			}
			return chr;
		} )
		.join( '' );
}

/**
 * DateTime Preview Initialization
 *
 * This script initializes the DateTime Preview functionality for all elements
 * with the attribute 'data-gatherpress_component_name' set to 'datetime-preview'.
 * It iterates through all matching elements and initializes a DateTimePreview component
 * with the attributes provided in the 'data-gatherpress_component_attrs' attribute.
 *
 * @since 1.0.0
 */
export function dateTimePreview() {
	// Select all elements with the attribute 'data-gatherpress_component_name' set to 'datetime-preview'.
	const dateTimePreviewContainers = document.querySelectorAll(
		`[data-gatherpress_component_name="datetime-preview"]`,
	);

	// Iterate through each matched element and initialize DateTimePreview component.
	for ( let i = 0; i < dateTimePreviewContainers.length; i++ ) {
		// Parse attributes from the 'data-gatherpress_component_attrs' attribute.
		const attrs = JSON.parse(
			dateTimePreviewContainers[ i ].dataset.gatherpress_component_attrs,
		);

		// Create a root element and render the DateTimePreview component with the parsed attributes.
		createRoot( dateTimePreviewContainers[ i ] ).render(
			<DateTimePreview attrs={ attrs } />,
		);
	}
}

/**
 * Non-time PHP Date format characters
 *
 * @since 1.0.0
 *
 * @see https://www.php.net/manual/en/datetime.format.php
 *
 * @type {Array}
 */
export const phpNonTimeFormatChars = [
	'd',
	'D',
	'j',
	'l',
	'N',
	'S',
	'w',
	'z',
	'W',
	'F',
	'm',
	'M',
	'n',
	't',
	'L',
	'o',
	'X',
	'x',
	'Y',
	'y',
	'e',
	'I',
	'O',
	'P',
	'p',
	'T',
	'Z',
	'c',
	'r',
	'U',
	',',
];

/**
 * Remove non-time characters from PHP format string
 *
 * @since 1.0.0
 *
 * @param {string} format - The PHP datetime format.
 * @return {string} The PHP time-only format.
 */
export function removeNonTimePHPFormatChars( format ) {
	return format
		.split( '' )
		.filter( ( char ) => ! phpNonTimeFormatChars.includes( char ) )
		.join( '' )
		.trim();
}
