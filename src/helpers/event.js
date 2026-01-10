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
import { createMomentWithTimezone, getTimezone } from './datetime';
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

		// Check if this is the current post being edited in the editor.
		const currentPostId = select( 'core/editor' )?.getCurrentPostId();
		const isCurrentPost = currentPostId && currentPostId === postId;

		// If editing this post in the editor, it's valid regardless of status.
		// Otherwise, check if it's published.
		if ( isCurrentPost ) {
			return !! post;
		}

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
	const timezone = getTimezone();
	const dateTimeEnd = createMomentWithTimezone(
		getFromGlobal( 'eventDetails.dateTime.datetime_end' ),
		timezone,
	);

	// Get current time in the event timezone.
	const now = createMomentWithTimezone(
		moment().format( 'YYYY-MM-DD HH:mm:ss' ),
		timezone,
	);

	return (
		'gatherpress_event' === select( 'core/editor' )?.getCurrentPostType() &&
		now.valueOf() > dateTimeEnd.valueOf()
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

/**
 * Gets event meta data (max guest limit and anonymous RSVP setting).
 *
 * This function retrieves event meta data either from the current post being edited
 * (for live updates) or from a specific post (for overrides). It handles three scenarios:
 * 1. Explicit override - attributes.postId is set (uses saved data from that post)
 * 2. Context postId - postId from block context (uses live editor data)
 * 3. No postId - checks if current post is an event (uses live editor data)
 *
 * @since 1.0.0
 *
 * @param {Object}      selectFunc WordPress data select function.
 * @param {number|null} postId     Post ID from context or null.
 * @param {Object}      attributes Block attributes (may contain explicit postId override).
 * @return {Object} Object containing maxGuestLimit and enableAnonymousRsvp.
 */
export function getEventMeta( selectFunc, postId, attributes ) {
	let maxLimit;
	let enableAnonymous;

	// Check if there's an explicit postId override in attributes.
	// If attributes.postId exists, it's an override - use entity record.
	// If postId only comes from context, use editor for live edits.
	const hasExplicitOverride = !! attributes?.postId;

	if ( hasExplicitOverride && postId ) {
		// Explicit override - fetch from post via core data store.
		const post = selectFunc( 'core' ).getEntityRecord( 'postType', 'gatherpress_event', postId );
		maxLimit = post?.meta?.gatherpress_max_guest_limit;
		enableAnonymous = Boolean( post?.meta?.gatherpress_enable_anonymous_rsvp );
	} else {
		// No override - check if current post is an event and use editor for live edits.
		const currentPostType = selectFunc( 'core/editor' )?.getCurrentPostType();
		const isCurrentPostEvent = 'gatherpress_event' === currentPostType;

		if ( isCurrentPostEvent ) {
			const meta = selectFunc( 'core/editor' ).getEditedPostAttribute( 'meta' );
			maxLimit = meta?.gatherpress_max_guest_limit;
			enableAnonymous = Boolean( meta?.gatherpress_enable_anonymous_rsvp );
		}
	}

	return {
		maxGuestLimit: maxLimit ?? 0,
		enableAnonymousRsvp: enableAnonymous ?? false,
	};
}

