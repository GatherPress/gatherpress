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
import { enableSave, getFromGlobal, setToGlobal } from './misc';
import { isEventPostType } from './event';

export const dateTimeMomentFormat = 'YYYY-MM-DDTHH:mm:ss';
export const dateTimeDatabaseFormat = 'YYYY-MM-DD HH:mm:ss';
export const dateTimeLabelFormat = 'MMMM D, YYYY h:mm a';

const getTimeZone = () => {
	const timezone = getFromGlobal('event_datetime.timezone');

	if (!!moment.tz.zone(timezone)) {
		return timezone;
	}

	return __('GMT', 'gatherpress');
};

export const timeZone = getTimeZone();

const getUtcOffset = () => {
	if (__('GMT', 'gatherpress') !== timeZone) {
		return '';
	}

	const offset = getFromGlobal('event_datetime.timezone');

	return 'string' === typeof offset ? offset.replace(':', '') : '';
};

export const utcOffset = getUtcOffset();

// export const defaultDateTimeStart = undefined;
export const defaultDateTimeStart = moment
	.tz(timeZone)
	.add(1, 'day')
	.set('hour', 18)
	.set('minute', 0)
	.set('second', 0)
	.format(dateTimeMomentFormat);

export const getDateTimeStart = () => {
	let dateTime = getFromGlobal('event_datetime.datetime_start');

	dateTime =
		'' !== dateTime
			? moment.tz(dateTime, timeZone).format(dateTimeMomentFormat)
			: defaultDateTimeStart;

	setToGlobal('event_datetime.datetime_start', dateTime);

	return dateTime;
};

export const getDateTimeEnd = () => {
	let dateTime = getFromGlobal('event_datetime.datetime_end');

	dateTime =
		'' !== dateTime
			? moment.tz(dateTime, timeZone).format(dateTimeMomentFormat)
			: moment
					.tz(defaultDateTimeStart, timeZone)
					.add(2, 'hours')
					.format(dateTimeMomentFormat);

	setToGlobal('event_datetime.datetime_end', dateTime);

	return dateTime;
};

export const updateDateTimeStart = (date, setDateTimeStart = null) => {
	validateDateTimeStart(date);

	setToGlobal('event_datetime.datetime_start', date);

	if ('function' === typeof setDateTimeStart) {
		setDateTimeStart(date);
	}

	enableSave();
};

export const updateDateTimeEnd = (date, setDateTimeEnd = null) => {
	validateDateTimeEnd(date);

	setToGlobal('event_datetime.datetime_end', date);

	if (null !== setDateTimeEnd) {
		setDateTimeEnd(date);
	}

	enableSave();
};

export function validateDateTimeStart(dateTimeStart) {
	const dateTimeEndNumeric = moment
		.tz(getFromGlobal('event_datetime.datetime_end'), timeZone)
		.valueOf();
	const dateTimeStartNumeric = moment.tz(dateTimeStart, timeZone).valueOf();

	if (dateTimeStartNumeric >= dateTimeEndNumeric) {
		const dateTimeEnd = moment
			.tz(dateTimeStartNumeric, timeZone)
			.add(2, 'hours')
			.format(dateTimeMomentFormat);

		updateDateTimeEnd(dateTimeEnd);
	}
}

export function validateDateTimeEnd(dateTimeEnd) {
	const dateTimeStartNumeric = moment
		.tz(getFromGlobal('event_datetime.datetime_start'), timeZone)
		.valueOf();
	const dateTimeEndNumeric = moment.tz(dateTimeEnd, timeZone).valueOf();

	if (dateTimeEndNumeric <= dateTimeStartNumeric) {
		const dateTimeStart = moment
			.tz(dateTimeEndNumeric, timeZone)
			.subtract(2, 'hours')
			.format(dateTimeMomentFormat);
		updateDateTimeStart(dateTimeStart);
	}
}

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
						timeZone
					)
					.format(dateTimeDatabaseFormat),
				datetime_end: moment
					.tz(getFromGlobal('event_datetime.datetime_end'), timeZone)
					.format(dateTimeDatabaseFormat),
				timezone: getFromGlobal('event_datetime.timezone'),
				_wpnonce: getFromGlobal('nonce'),
			},
		}).then(() => {
			// Saved.
		});
	}
}
