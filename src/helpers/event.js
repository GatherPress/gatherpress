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

export function CheckCurrentPostType() {
	return select('core/editor').getCurrentPostType();
}

export function hasEventPast() {
	const dateTimeEnd = moment(getFromGlobal('event_datetime.datetime_end'));

	return (
		moment.tz(getTimeZone()).valueOf() >
		dateTimeEnd.tz(getTimeZone()).valueOf()
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

export function triggerEventCommuncation() {
	const id = 'gp_event_communcation';
	const notices = dispatch('core/notices');

	notices.removeNotice(id);

	if (
		select('core/editor').getEditedPostAttribute('status') === 'publish' &&
		!hasEventPast()
	) {
		notices.createNotice(
			'success',
			__('Update members about this event via email?', 'gatherpress'),
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
						label: __('Create Message', 'gatherpress'),
					},
				],
			}
		);
	}
}
