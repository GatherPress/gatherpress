/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { isEventPostType } from '../../helpers';

/**
 * Internal dependencies.
 */
import { updateDateTimeStart } from './datetime-start/label';
import { updateDateTimeEnd, hasEventPastNotice } from './datetime-end/label';

export const dateTimeFormat = 'YYYY-MM-DDTHH:mm:ss';

export function validateDateTimeStart(dateTime) {
	const dateTimeEndNumeric = moment(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_end
	).valueOf();
	const dateTimeNumeric = moment(dateTime).valueOf();

	if (dateTimeNumeric >= dateTimeEndNumeric) {
		const dateTimeEnd = moment(dateTimeNumeric)
			.add(2, 'hours')
			.format(dateTimeFormat);
		updateDateTimeEnd(dateTimeEnd);
	}

	hasEventPastNotice();
}

export function validateDateTimeEnd(dateTime) {
	const dateTimeStartNumeric = moment(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_start
	).valueOf();
	const dateTimeNumeric = moment(dateTime).valueOf();

	if (dateTimeNumeric <= dateTimeStartNumeric) {
		const dateTimeStart = moment(dateTimeNumeric)
			.subtract(2, 'hours')
			.format(dateTimeFormat);
		updateDateTimeStart(dateTimeStart);
	}

	hasEventPastNotice();
}

// @todo maybe put this is a save_post hook.
// https://www.ibenic.com/use-wordpress-hooks-package-javascript-apps/
// Then move button enabler
export function saveDateTime() {
	const isSavingPost = wp.data.select('core/editor').isSavingPost(),
		isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();

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
				).format('YYYY-MM-DD HH:mm:ss'),
				datetime_end: moment(
					// eslint-disable-next-line no-undef
					GatherPress.event_datetime.datetime_end
				).format('YYYY-MM-DD HH:mm:ss'),
				// eslint-disable-next-line no-undef
				_wpnonce: GatherPress.nonce,
			},
		}).then(() => {
			// Saved.
		});
	}
}
