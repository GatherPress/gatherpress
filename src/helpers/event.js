/**
 * External dependencies.
 */
import moment from 'moment/moment';

/**
 * WordPress dependencies.
 */
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export function isEventPostType() {
	return 'gp_event' === select('core/editor').getCurrentPostType();
}

export function hasEventPast() {
	return (
		moment().valueOf() >
		// eslint-disable-next-line no-undef
		moment(GatherPress.event_datetime.datetime_end).valueOf()
	);
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
