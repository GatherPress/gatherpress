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

/**
 * Opacity value for disabled form fields and elements.
 *
 * This constant defines the opacity level applied to form fields and UI elements
 * when they are disabled due to event settings (e.g., when guest limits are 0
 * or anonymous RSVP is disabled).
 *
 * @since 1.0.0
 * @type {number}
 */
export const DISABLED_FIELD_OPACITY = 0.3;

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
	return 'gatherpress_event' === select( 'core/editor' )?.getCurrentPostType();
}

/**
 * Checks if a block has a valid event ID (either from current post or postId override).
 *
 * This function checks if the block is connected to a valid event, either by being
 * placed in an event post or having a postId attribute that points to a valid event.
 *
 * @since 1.0.0
 *
 * @param {number|null} postId Optional post ID override to check.
 * @return {boolean} True if connected to a valid event, false otherwise.
 */
export function hasValidEventId( postId = null ) {
	// If postId is provided, verify it points to a valid, published event.
	if ( postId ) {
		const post = select( 'core' ).getEntityRecord( 'postType', 'gatherpress_event', postId );
		return !! post && 'publish' === post.status;
	}

	// Otherwise, check if current post is an event (no publish check needed).
	return isEventPostType();
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
		getFromGlobal( 'eventDetails.dateTime.datetime_end' ),
		getTimezone(),
	);

	return (
		'gatherpress_event' === select( 'core/editor' )?.getCurrentPostType() &&
		moment.tz( getTimezone() ).valueOf() > dateTimeEnd.valueOf()
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
	const notices = dispatch( 'core/notices' );

	notices.removeNotice( id );

	if ( hasEventPast() ) {
		notices.createNotice(
			'warning',
			__( 'This event has already passed.', 'gatherpress' ),
			{
				id,
				isDismissible: false,
			},
		);
	}
}

