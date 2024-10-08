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
import { getTimezone } from './datetime';
import { getFromGlobal } from './globals';
import { Broadcaster } from './broadcasting';

/**
 * Checks if the current post type is an event in the GatherPress application.
 *
 * This function queries the current post type using the `select` function from the `core/editor` package.
 * It returns `true` if the current post type is 'gatherpress_event', indicating that the post is an event,
 * and `false` otherwise.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is 'gatherpress_event', false otherwise.
 */
export function isEventPostType() {
	return 'gatherpress_event' === select('core/editor')?.getCurrentPostType();
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
		getFromGlobal('eventDetails.dateTime.datetime_end'),
		getTimezone()
	);

	return (
		'gatherpress_event' === select('core/editor')?.getCurrentPostType() &&
		moment.tz(getTimezone()).valueOf() > dateTimeEnd.valueOf()
	);
}

/**
 * Display a notice if the event has already passed.
 *
 * This function checks if the event has passed and displays a warning notice
 * if so. The notice is non-dismissible to ensure the user is informed about
 * the event status.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
export function hasEventPastNotice() {
	const id = 'gatherpress_event_past';
	const notices = dispatch('core/notices');

	notices.removeNotice(id);

	if (hasEventPast()) {
		notices.createNotice(
			'warning',
			__('This event has already passed.', 'gatherpress'),
			{
				id,
				isDismissible: false,
			}
		);
	}
}

/**
 * Flag to prevent multiple event communication notices.
 *
 * @type {boolean}
 */
let isEventCommunicationNoticeCreated = false;

/**
 * Trigger communication notice for event updates.
 *
 * This function checks if the event is published and not yet passed,
 * then displays a success notice prompting the user to send an event update
 * to members via email. The notice includes an action to compose the message.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
export function triggerEventCommunication() {
	const id = 'gatherpress_event_communication';
	const notices = dispatch('core/notices');
	const isSavingPost = select('core/editor').isSavingPost();
	const isAutosavingPost = select('core/editor').isAutosavingPost();

	// Only proceed if a save is in progress and it's not an autosave.
	if (
		'publish' === select('core/editor').getEditedPostAttribute('status') &&
		isEventPostType() &&
		isSavingPost &&
		!isAutosavingPost &&
		!hasEventPast() &&
		!isEventCommunicationNoticeCreated
	) {
		// Mark notice as created.
		isEventCommunicationNoticeCreated = true;

		// Remove any previous notices with the same ID.
		notices.removeNotice(id);

		// Create a new notice with an action.
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

	// Reset the flag after the save operation completes.
	if (!isSavingPost) {
		isEventCommunicationNoticeCreated = false;
	}
}
