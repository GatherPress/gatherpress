/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { enableSave, getFromGlobal, setToGlobal } from './globals';
import { isEventPostType, triggerEventCommuncation } from './event';

/**
 * Date and time format string for use with Moment.js.
 *
 * This format is designed to represent date and time in the format
 * "YYYY-MM-DDTHH:mm:ss" for compatibility with Moment.js library.
 *
 * @since 1.0.0
 *
 * @type {string}
 */
export const dateTimeMomentFormat = 'YYYY-MM-DDTHH:mm:ss';

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
	.tz(getTimeZone())
	.add(1, 'day')
	.set('hour', 18)
	.set('minute', 0)
	.set('second', 0)
	.format(dateTimeMomentFormat);

/**
 * The default end date and time for an event.
 * It is calculated based on the default start date and time plus two hours in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default end date and time in the application's timezone.
 */
export const defaultDateTimeEnd = moment
	.tz(defaultDateTimeStart, getTimeZone())
	.add(2, 'hours')
	.format(dateTimeMomentFormat);

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
		getFromGlobal('settings.date_format')
	);
	const timeFormat = convertPHPToMomentFormat(
		getFromGlobal('settings.time_format')
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
export function getTimeZone(
	timezone = getFromGlobal('event_datetime.timezone')
) {
	if (!!moment.tz.zone(timezone)) {
		return timezone;
	}

	return __('GMT', 'gatherpress');
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
export function getUtcOffset(timezone) {
	timezone = getTimeZone(timezone);

	if (__('GMT', 'gatherpress') !== timezone) {
		return '';
	}

	const offset = getFromGlobal('event_datetime.timezone');

	return maybeConvertUtcOffsetForDisplay(offset);
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
export function maybeConvertUtcOffsetForDisplay(offset = '') {
	return offset.replace(':', '');
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
export function maybeConvertUtcOffsetForDatabase(offset = '') {
	// Regex: https://regex101.com/r/9bMgJd/2.
	const pattern = /^UTC([+-])(\d+)(.\d+)?$/;
	const sign = offset.replace(pattern, '$1');

	if (sign !== offset) {
		const hour = offset.replace(pattern, '$2').padStart(2, '0');
		let minute = offset.replace(pattern, '$3');

		if ('' === minute) {
			minute = ':00';
		}

		minute = minute
			.replace('.25', ':15')
			.replace('.5', ':30')
			.replace('.75', ':45');

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
export function maybeConvertUtcOffsetForSelect(offset = '') {
	// Regex: https://regex101.com/r/nOXCPo/2.
	const pattern = /^([+-])(\d{2}):(00|15|30|45)$/;
	const sign = offset.replace(pattern, '$1');

	if (sign !== offset) {
		const hour = parseInt(offset.replace(pattern, '$2')).toString();
		const minute = offset
			.replace(pattern, '$3')
			.replace('00', '')
			.replace('15', '.25')
			.replace('30', '.5')
			.replace('45', '.75');

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
	let dateTime = getFromGlobal('event_datetime.datetime_start');

	dateTime =
		'' !== dateTime
			? moment.tz(dateTime, getTimeZone()).format(dateTimeMomentFormat)
			: defaultDateTimeStart;

	setToGlobal('event_datetime.datetime_start', dateTime);

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
	let dateTime = getFromGlobal('event_datetime.datetime_end');

	dateTime =
		'' !== dateTime
			? moment.tz(dateTime, getTimeZone()).format(dateTimeMomentFormat)
			: defaultDateTimeEnd;

	setToGlobal('event_datetime.datetime_end', dateTime);

	return dateTime;
}

/**
 * Updates the start date and time for an event, performs validation, and triggers the save functionality.
 *
 * @since 1.0.0
 *
 * @param {string}   date             - The new start date and time to be set.
 * @param {Function} setDateTimeStart - Optional callback function to update the state or perform additional actions.
 *
 * @return {void}
 */
export function updateDateTimeStart(date, setDateTimeStart = null) {
	validateDateTimeStart(date);

	setToGlobal('event_datetime.datetime_start', date);

	if ('function' === typeof setDateTimeStart) {
		setDateTimeStart(date);
	}

	enableSave();
}

/**
 * Update the end date and time of the event and trigger necessary actions.
 *
 * This function sets the end date and time of the event to the specified value,
 * validates the input, and triggers additional actions such as updating the UI.
 *
 * @since 1.0.0
 *
 * @param {string}        date           - The new end date and time in a valid format.
 * @param {Function|null} setDateTimeEnd - Optional callback to update the UI with the new end date and time.
 *
 * @return {void}
 */
export function updateDateTimeEnd(date, setDateTimeEnd = null) {
	validateDateTimeEnd(date);

	setToGlobal('event_datetime.datetime_end', date);

	if (null !== setDateTimeEnd) {
		setDateTimeEnd(date);
	}

	enableSave();
}

/**
 * Validate the start date and time of the event and perform necessary adjustments if needed.
 *
 * This function compares the provided start date and time with the current end date
 * and time of the event. If the start date is greater than or equal to the end date,
 * it adjusts the end date to ensure a minimum two-hour duration.
 *
 * @since 1.0.0
 *
 * @param {string} dateTimeStart - The start date and time in a valid format.
 *
 * @return {void}
 */
export function validateDateTimeStart(dateTimeStart) {
	const dateTimeEndNumeric = moment
		.tz(getFromGlobal('event_datetime.datetime_end'), getTimeZone())
		.valueOf();
	const dateTimeStartNumeric = moment
		.tz(dateTimeStart, getTimeZone())
		.valueOf();

	if (dateTimeStartNumeric >= dateTimeEndNumeric) {
		const dateTimeEnd = moment
			.tz(dateTimeStartNumeric, getTimeZone())
			.add(2, 'hours')
			.format(dateTimeMomentFormat);

		updateDateTimeEnd(dateTimeEnd);
	}
}

/**
 * Validate the end date and time of the event and perform necessary adjustments if needed.
 *
 * This function compares the provided end date and time with the current start date
 * and time of the event. If the end date is less than or equal to the start date,
 * it adjusts the start date to ensure a minimum two-hour duration.
 *
 * @since 1.0.0
 *
 * @param {string} dateTimeEnd - The end date and time in a valid format.
 *
 * @return {void}
 */
export function validateDateTimeEnd(dateTimeEnd) {
	const dateTimeStartNumeric = moment
		.tz(getFromGlobal('event_datetime.datetime_start'), getTimeZone())
		.valueOf();
	const dateTimeEndNumeric = moment.tz(dateTimeEnd, getTimeZone()).valueOf();

	if (dateTimeEndNumeric <= dateTimeStartNumeric) {
		const dateTimeStart = moment
			.tz(dateTimeEndNumeric, getTimeZone())
			.subtract(2, 'hours')
			.format(dateTimeMomentFormat);
		updateDateTimeStart(dateTimeStart);
	}
}

/**
 * Save the event date, time, and timezone to the server.
 *
 * This function sends a POST request to the server with the updated event date,
 * time, and timezone information for storage. It is triggered during the process
 * of saving an event post in the WordPress editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
export function saveDateTime() {
	const isSavingPost = select('core/editor').isSavingPost(),
		isAutosavingPost = select('core/editor').isAutosavingPost();

	if (isEventPostType() && isSavingPost && !isAutosavingPost) {
		apiFetch({
			path: '/gatherpress/v1/event/datetime/',
			method: 'POST',
			data: {
				post_id: getFromGlobal('post_id'),
				datetime_start: moment
					.tz(
						getFromGlobal('event_datetime.datetime_start'),
						getTimeZone()
					)
					.format(dateTimeDatabaseFormat),
				datetime_end: moment
					.tz(
						getFromGlobal('event_datetime.datetime_end'),
						getTimeZone()
					)
					.format(dateTimeDatabaseFormat),
				timezone: getFromGlobal('event_datetime.timezone'),
				_wpnonce: getFromGlobal('nonce'),
			},
		}).then(() => {
			triggerEventCommuncation();
		});
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
export function convertPHPToMomentFormat(format) {
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

	return String(format)
		.split('')
		.map((chr) => (chr in replacements ? replacements[chr] : chr))
		.join('');
}
