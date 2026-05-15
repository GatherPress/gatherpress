/**
 * External dependencies
 */
import moment from 'moment';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createRoot, useMemo } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { select, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getFromSettings } from './editor-settings';
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
 * Get the default start date and time for an event.
 * It is set to the current date and time plus one day at 18:00:00 in the application's timezone.
 *
 * @since 1.0.0
 *
 * @return {string} Formatted default start date and time in the application's timezone.
 */
function getDefaultDateTimeStart() {
	const timezone = getTimezone();
	return createMomentWithTimezone(
		moment().format( 'YYYY-MM-DD HH:mm:ss' ),
		timezone
	)
		.add( 1, 'day' )
		.set( 'hour', 18 )
		.set( 'minute', 0 )
		.set( 'second', 0 )
		.format( dateTimeDatabaseFormat );
}

/**
 * The default start date and time for an event.
 * It is set to the current date and time plus one day at 18:00:00 in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default start date and time in the application's timezone.
 */
export const defaultDateTimeStart = getDefaultDateTimeStart();

/**
 * Get the default end date and time for an event.
 * It is calculated based on the default start date and time plus two hours in the application's timezone.
 *
 * @since 1.0.0
 *
 * @return {string} Formatted default end date and time in the application's timezone.
 */
function getDefaultDateTimeEnd() {
	const timezone = getTimezone();
	const startDateTime = getDefaultDateTimeStart();
	return createMomentWithTimezone( startDateTime, timezone )
		.add( 2, 'hours' )
		.format( dateTimeDatabaseFormat );
}

/**
 * The default end date and time for an event.
 * It is calculated based on the default start date and time plus two hours in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default end date and time in the application's timezone.
 */
export const defaultDateTimeEnd = getDefaultDateTimeEnd();

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
	return createMomentWithTimezone( getDateTimeStart(), getTimezone() )
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
 * Pure matched-preset lookup. Given a start/end/timezone/duration tuple,
 * returns the duration option whose `(start + value hours)` matches the
 * given end, or `false` when no preset matches (or when the caller has
 * explicitly opted out by passing `false` for `duration`).
 *
 * Extracted from `useMatchedDuration` so the matching logic is testable
 * in isolation — the hook is just a `useSelect`/`useMemo` wrapper around
 * this function.
 *
 * @since 1.0.0
 *
 * @param {string}         dateTimeStart Start datetime string.
 * @param {string}         dateTimeEnd   End datetime string.
 * @param {string}         timezone      Timezone (IANA name or manual offset).
 * @param {number|boolean} duration      Raw stored duration: `false` to
 *                                       opt out, anything else to compute.
 * @return {number|boolean} Matched duration option value, or `false`.
 */
export function findMatchedDuration(
	dateTimeStart,
	dateTimeEnd,
	timezone,
	duration,
) {
	if ( false === duration ) {
		return false;
	}
	return (
		durationOptions().find( ( option ) => {
			const computedEnd = createMomentWithTimezone(
				dateTimeStart,
				timezone,
			)
				.add( option.value, 'hours' )
				.format( dateTimeDatabaseFormat );
			return computedEnd === dateTimeEnd;
		} )?.value || false
	);
}

/**
 * Reactive, memoized matched-preset duration for the event datetime range.
 *
 * Returns the duration option whose `(start + value hours)` matches the
 * current end, or `false` when no preset matches (or when the user has
 * explicitly opted out via `setDuration(false)`). Components use this to
 * decide between rendering `<Duration />` (preset mode) vs `<DateTimeEnd />`
 * (absolute mode) and to drive the duration `<SelectControl>`'s value.
 *
 * Why a hook instead of a store selector: the previous `getDuration`
 * selector ran the full `dateTimeOffset` × N moment.tz comparison on every
 * call, which @wordpress/data invokes once per subscriber per render. Under
 * IANA timezones the multiplied moment.tz cost compounded with the WP
 * picker's render cascade and overflowed the call stack on a single
 * year-arrow keypress (#1607). Computing in a `useMemo` keyed on the
 * actual inputs runs the comparison once per real change instead.
 *
 * @since 1.0.0
 *
 * @return {number|boolean} Matched duration option value, or `false`.
 */
export function useMatchedDuration() {
	const dateTimeStart = useSelect(
		( s ) => s( 'gatherpress/datetime' ).getDateTimeStart(),
		[],
	);
	const dateTimeEnd = useSelect(
		( s ) => s( 'gatherpress/datetime' ).getDateTimeEnd(),
		[],
	);
	const timezone = useSelect(
		( s ) => s( 'gatherpress/datetime' ).getTimezone(),
		[],
	);
	const duration = useSelect(
		( s ) => s( 'gatherpress/datetime' ).getDuration(),
		[],
	);

	return useMemo(
		() =>
			findMatchedDuration( dateTimeStart, dateTimeEnd, timezone, duration ),
		[ dateTimeStart, dateTimeEnd, timezone, duration ],
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
		getFromSettings( 'dateFormat' ),
	);
	const timeFormat = convertPHPToMomentFormat(
		getFromSettings( 'timeFormat' ),
	);

	return dateFormat + ' ' + timeFormat;
}

/**
 * Checks if a timezone string is a manual offset (like +05:00 or -12:00).
 *
 * Manual offsets start with + or - and cannot be used with moment.tz().
 *
 * @since 1.0.0
 *
 * @param {string} timezone - The timezone string to check.
 *
 * @return {boolean} True if the timezone is a manual offset, false otherwise.
 */
export function isManualOffset( timezone ) {
	return /^[+-]\d{2}:\d{2}$/.test( timezone );
}

/**
 * Creates a moment object with the correct timezone handling.
 *
 * For IANA timezone identifiers (like 'America/New_York'), uses moment.tz().
 * For manual offsets (like '+05:00'), uses moment with utcOffset, keeping local time.
 *
 * @since 1.0.0
 *
 * @param {string} datetime - The datetime string to parse.
 * @param {string} timezone - The timezone or offset to use.
 *
 * @return {Object} A moment object with the correct timezone applied.
 */
export function createMomentWithTimezone( datetime, timezone ) {
	if ( isManualOffset( timezone ) ) {
		// For manual offsets, parse the datetime and apply the offset while keeping the local time.
		// The 'true' parameter keeps the local time the same.
		return moment( datetime ).utcOffset( timezone, true );
	}

	// For IANA timezone identifiers, use moment.tz().
	return moment.tz( datetime, timezone );
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
	timezone = select( 'gatherpress/datetime' )?.getTimezone?.() ?? '',
) {
	// Manual offsets (like +05:00) are valid, return as-is.
	if ( isManualOffset( timezone ) ) {
		return timezone;
	}

	// For IANA timezone identifiers, validate with moment.tz.
	if ( moment.tz.zone( timezone ) ) {
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

	if ( __( 'GMT', 'gatherpress' ) === timezone ) {
		const offset =
			select( 'gatherpress/datetime' )?.getTimezone?.() ?? '';

		return maybeConvertUtcOffsetForDisplay( offset );
	}

	return '';
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

	if ( sign === offset ) {
		return offset;
	}

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
	const dateTime =
		select( 'gatherpress/datetime' )?.getDateTimeStart?.() ?? '';

	return '' === dateTime
		? defaultDateTimeStart
		: createMomentWithTimezone( dateTime, getTimezone() ).format(
			dateTimeDatabaseFormat,
		);
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
	const dateTime =
		select( 'gatherpress/datetime' )?.getDateTimeEnd?.() ?? '';

	return '' === dateTime
		? defaultDateTimeEnd
		: createMomentWithTimezone( dateTime, getTimezone() ).format(
			dateTimeDatabaseFormat,
		);
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
	// Capture the matched preset BEFORE we dispatch the new start so the
	// lookup runs against the previous start/end pair — we're trying to
	// detect "was the event in relative (preset-duration) mode?", which is
	// a property of the OLD state.
	const currentDuration = getDateTimeOffset();

	// Dispatch the new start FIRST so the validation cascade below — which
	// reads the start back via `select( 'gatherpress/datetime' )...` — sees
	// the new value rather than the stale one. Without this, year-down on
	// the start picker in relative mode (#1607) computed a new end that's
	// less than the OLD store start, `validateDateTimeEnd` then recursively
	// called `updateDateTimeStart` to fix the gap, and the recursion never
	// terminated because the store never got updated inside the synchronous
	// chain. Stack overflowed inside `moment.tz`. This mirrors the previous
	// `setToGlobal( 'eventDetails.dateTime.datetime_start', date )` write
	// that the old global-object architecture used to perform here.
	if ( 'function' === typeof setDateTimeStart ) {
		setDateTimeStart( date );
	}

	// If in relative mode (duration is numeric), always update the end time to maintain the offset.
	if ( 'number' === typeof currentDuration ) {
		const dateTimeEnd = createMomentWithTimezone( date, getTimezone() )
			.add( currentDuration, 'hours' )
			.format( dateTimeDatabaseFormat );

		updateDateTimeEnd( dateTimeEnd, setDateTimeEnd );
	} else {
		// Otherwise, only validate to ensure end is after start.
		validateDateTimeStart( date, setDateTimeEnd, currentDuration );
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
	// Dispatch the new end FIRST so any subsequent reads of the end via
	// `select( 'gatherpress/datetime' ).getDateTimeEnd()` (e.g. through
	// `validateDateTimeStart` if a recursive call back into the start path
	// fires) see the new value rather than the stale store value. Same
	// reasoning as the matching reorder in `updateDateTimeStart` (#1607).
	if ( null !== setDateTimeEnd ) {
		setDateTimeEnd( date );
	}

	validateDateTimeEnd( date, setDateTimeStart );

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
	const tz = getTimezone();
	const dateTimeEndNumeric = createMomentWithTimezone(
		select( 'gatherpress/datetime' )?.getDateTimeEnd?.() ?? '',
		tz,
	).valueOf();
	const dateTimeStartNumeric = createMomentWithTimezone(
		dateTimeStart,
		tz,
	).valueOf();

	if ( dateTimeStartNumeric >= dateTimeEndNumeric ) {
		// Use the passed duration if available, otherwise check current offset.
		// Only use duration if it's numeric (relative mode), not if it's false (absolute mode).
		const duration = null === currentDuration ? getDateTimeOffset() : currentDuration;
		const hoursToAdd = ( false !== duration && 'number' === typeof duration ) ? duration : 2;

		const dateTimeEnd = createMomentWithTimezone( dateTimeStartNumeric, tz )
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
	const tz = getTimezone();
	const dateTimeStartNumeric = createMomentWithTimezone(
		select( 'gatherpress/datetime' )?.getDateTimeStart?.() ?? '',
		tz,
	).valueOf();
	const dateTimeEndNumeric = createMomentWithTimezone(
		dateTimeEnd,
		tz,
	).valueOf();

	if ( dateTimeEndNumeric <= dateTimeStartNumeric ) {
		const dateTimeStart = createMomentWithTimezone( dateTimeEndNumeric, tz )
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
	for ( const container of dateTimePreviewContainers ) {
		// Parse attributes from the 'data-gatherpress_component_attrs' attribute.
		const attrs = JSON.parse(
			container.dataset.gatherpress_component_attrs,
		);

		// Create a root element and render the DateTimePreview component with the parsed attributes.
		createRoot( container ).render(
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
