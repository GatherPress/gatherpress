/**
 * External dependencies.
 */
import moment from 'moment/moment';

/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { Broadcaster } from './broadcasting';
import { enableSave } from './misc';
import { isEventPostType } from './event';

export const dateTimeMomentFormat = 'YYYY-MM-DDTHH:mm:ss';
export const dateTimeDatabaseFormat = 'YYYY-MM-DD HH:mm:ss';
export const defaultDateTimeStart = moment()
	.add(1, 'day')
	.set('hour', 18)
	.set('minute', 0)
	.format(dateTimeMomentFormat);

export const getDateTimeStart = () => {
	// eslint-disable-next-line no-undef
	let dateTime = GatherPress.event_datetime.datetime_start;

	dateTime =
		'' !== dateTime
			? moment(dateTime).format(dateTimeMomentFormat)
			: defaultDateTimeStart;

	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_start = dateTime;

	return dateTime;
};

export const getDateTimeEnd = () => {
	// eslint-disable-next-line no-undef
	let dateTime = GatherPress.event_datetime.datetime_end;

	dateTime =
		'' !== dateTime
			? moment(dateTime).format(dateTimeMomentFormat)
			: moment(defaultDateTimeStart)
					.add(2, 'hours')
					.format(dateTimeMomentFormat);

	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_end = dateTime;

	return dateTime;
};

export const updateDateTimeStart = (date, setDateTimeStart = null) => {
	validateDateTimeStart(date);

	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_start = date;

	if ('function' === typeof setDateTimeStart) {
		setDateTimeStart(date);
	}

	const payload = {
		setDateTimeStart: date,
	};

	Broadcaster(payload);
	enableSave();
};

export const updateDateTimeEnd = (date, setDateTimeEnd = null) => {
	validateDateTimeEnd(date);

	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_end = date;

	if (null !== setDateTimeEnd) {
		setDateTimeEnd(date);
	}

	const payload = {
		setDateTimeEnd: date,
	};

	Broadcaster(payload);
	enableSave();
};

export function validateDateTimeStart(dateTimeStart) {
	const dateTimeEndNumeric = moment(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_end
	).valueOf();
	const dateTimeStartNumeric = moment(dateTimeStart).valueOf();

	if (dateTimeStartNumeric >= dateTimeEndNumeric) {
		const dateTimeEnd = moment(dateTimeStartNumeric)
			.add(2, 'hours')
			.format(dateTimeMomentFormat);

		updateDateTimeEnd(dateTimeEnd);
	}
}

export function validateDateTimeEnd(dateTimeEnd) {
	const dateTimeStartNumeric = moment(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_start
	).valueOf();
	const dateTimeEndNumeric = moment(dateTimeEnd).valueOf();

	if (dateTimeEndNumeric <= dateTimeStartNumeric) {
		const dateTimeStart = moment(dateTimeEndNumeric)
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
				// eslint-disable-next-line no-undef
				post_id: GatherPress.post_id,
				datetime_start: moment(
					// eslint-disable-next-line no-undef
					GatherPress.event_datetime.datetime_start
				).format(dateTimeDatabaseFormat),
				datetime_end: moment(
					// eslint-disable-next-line no-undef
					GatherPress.event_datetime.datetime_end
				).format(dateTimeDatabaseFormat),
				// eslint-disable-next-line no-undef
				_wpnonce: GatherPress.nonce,
			},
		}).then(() => {
			// Saved.
		});
	}
}
