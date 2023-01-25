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

export const DateTimeStartLabel = (props) => {
	const { dateTimeStart } = props;

	return moment.tz(dateTimeStart, getTimeZone()).format(dateTimeLabelFormat);
};

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
