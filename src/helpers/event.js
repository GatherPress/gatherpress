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
import { getTimeZone } from './datetime';
import { getFromGlobal } from './globals';
import { Broadcaster } from './broadcasting';

export function isEventPostType() {
	return 'gp_event' === select('core/editor').getCurrentPostType();
}

/**
 * Check if the event has already passed.
 *
 * This function compares the current time with the end time of the event
 * to determine if the event has already taken place.
 *
 * @return {boolean} True if the event has passed; false otherwise.
 */
export function hasEventPast() {
	const dateTimeEnd = moment.tz(
		getFromGlobal('event_datetime.datetime_end'),
		getTimeZone()
	);

	return moment.tz(getTimeZone()).valueOf() > dateTimeEnd.valueOf();
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

export function triggerEventCommuncation() {
	const id = 'gp_event_communcation';
	const notices = dispatch('core/notices');

	notices.removeNotice(id);

	if (
		'publish' === select('core/editor').getEditedPostAttribute('status') &&
		!hasEventPast()
	) {
		notices.createNotice(
			'success',
			__('Send an event update to members via email?', 'gatherpress'),
			{
				id,
				isDismissible: true,
				actions: [
					{
						onClick: () => {
							Broadcaster({
								setOpen: true,
							});
						},
						label: __('Compose Message', 'gatherpress'),
					},
				],
			}
		);
	}
}
