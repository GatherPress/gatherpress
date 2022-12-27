/**
 * WordPress dependencies.
 */
import { dateI18n, getSettings } from '@wordpress/date';
import { DateTimePicker } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { updateDateTimeStart, updateDateTimeEnd } from '../helpers/datetime';

export const DateTimeStartLabel = (props) => {
	const { dateTimeStart } = props;
	const settings = getSettings();

	return dateI18n(
		`${settings.formats.date} ${settings.formats.time}`,
		dateTimeStart,
		false
	);
};

export const DateTimeEndLabel = (props) => {
	const { dateTimeEnd } = props;
	const settings = getSettings();

	return dateI18n(
		`${settings.formats.date} ${settings.formats.time}`,
		dateTimeEnd,
		false
	);
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
