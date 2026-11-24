/**
 * WordPress dependencies
 */
import { _n, sprintf } from '@wordpress/i18n';

/**
 * The ID of the editor notice that offers to tell attendees about a change.
 *
 * Shared by the notice and the email modal, which removes the notice once
 * the message is sent.
 *
 * @since TBD
 *
 * @type {string}
 */
export const CHANGE_NOTICE_ID = 'gatherpress_event_communication';

/**
 * The words of the notice for a change.
 *
 * @since TBD
 *
 * @param {Object} change The `gatherpress_change` REST field of the saved event.
 *
 * @return {string} What changed, and how many attendees the message reaches.
 */
export const getChangeNoticeText = ( change ) =>
	sprintf(
		/* translators: 1: What changed, for example "The venue changed." 2: Number of attendees. */
		_n(
			'%1$s Tell %2$d attendee?',
			'%1$s Tell %2$d attendees?',
			change.attendees,
			'gatherpress',
		),
		change.headline,
		change.attendees,
	);
