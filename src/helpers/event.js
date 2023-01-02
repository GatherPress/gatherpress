/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { timeZone } from './datetime';

export function isEventPostType() {
	return 'gp_event' === select('core/editor').getCurrentPostType();
}

export function CheckCurrentPostType() {
	return wp.data.select('core/editor').getCurrentPostType();
}

export function hasEventPast() {
	// eslint-disable-next-line no-undef
	const dateTimeEnd = moment(GatherPress.event_datetime.datetime_end);

	return moment.tz(timeZone).valueOf() > dateTimeEnd.tz(timeZone).valueOf();
}

export function hasEventPastNotice() {
	const id = 'gp_event_past';
	const notices = dispatch('core/notices');

	notices.removeNotice(id);

	if (hasEventPast()) {
		notices.createNotice(
			'warning',
			__('This event has already past.', 'gatherpress'),
			{
				id,
				isDismissible: false,
			}
		);
	}
}
