/**
 * WordPress dependencies.
 */
import { getSettings } from '@wordpress/date';
import { DateTimePicker } from '@wordpress/components';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import {
	updateDateTimeStart,
	updateDateTimeEnd,
	dateTimeLabelFormat,
	getTimeZone,
} from '../helpers/datetime';

/**
 * Formats the provided start date and time according to the specified label format
 * and returns the formatted result in the time zone configured for the plugin.
 *
 * @param {Object} props               - The properties object containing the start date and time.
 * @param {string} props.dateTimeStart - The start date and time to be formatted.
 *
 * @return {string} Formatted date and time label based on the configured format and time zone.
 */
export const DateTimeStartLabel = (props) => {
	const { dateTimeStart } = props;

	return moment.tz(dateTimeStart, getTimeZone()).format(dateTimeLabelFormat);
};

/**
 * Formats the provided end date and time according to the specified label format
 * and returns the formatted result in the time zone configured for the plugin.
 *
 * @param {Object} props               - The properties object containing the end date and time.
 * @param {string} props.dateTimeStart - The end date and time to be formatted.
 *
 * @return {string} Formatted date and time label based on the configured format and time zone.
 */
export const DateTimeEndLabel = (props) => {
	const { dateTimeEnd } = props;

	return moment.tz(dateTimeEnd, getTimeZone()).format(dateTimeLabelFormat);
};

export const DateTimeStartPicker = (props) => {
	const { dateTimeStart, setDateTimeStart } = props;
	const settings = getSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase()
			.replace(/\\\\/g, '')
			.split('')
			.reverse()
			.join('')
	);

	return (
		<DateTimePicker
			currentDate={dateTimeStart}
			onChange={(date) => updateDateTimeStart(date, setDateTimeStart)}
			is12Hour={is12HourTime}
		/>
	);
};

export const DateTimeEndPicker = (props) => {
	const { dateTimeEnd, setDateTimeEnd } = props;
	const settings = getSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase()
			.replace(/\\\\/g, '')
			.split('')
			.reverse()
			.join('')
	);

	return (
		<DateTimePicker
			currentDate={dateTimeEnd}
			onChange={(date) => updateDateTimeEnd(date, setDateTimeEnd)}
			is12Hour={is12HourTime}
		/>
	);
};
