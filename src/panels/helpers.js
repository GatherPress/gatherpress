/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { useDispatch } from '@wordpress/data';

// Checks if the post type is for events.
export function isEventPostType() {
	const getPostType = wp.data.select( 'core/editor' ).getCurrentPostType(); // Gets the current post type.

	return 'gp_event' === getPostType;
}

// @todo hack approach to enabling Save buttons after update
// https://github.com/WordPress/gutenberg/issues/13774
export function enableSave() {
	wp.data
		.dispatch( 'core/editor' )
		.editPost( { meta: { _non_existing_meta: true } } );
}

export function hasEventPastNotice() {
	const id = 'gp_event_past';
	// const notices = wp.data.dispatch( 'core/notices' );
	const { createErrorNotice } = useDispatch( noticesStore );

	notices.removeNotice( id );

	if ( hasEventPast() ) {
		createErrorNotice( __( 'This event has already past.', 'gatherpress' ), {
			type: id,
			explicitDismiss: true,
		} );
		// notices.createNotice(
		// 	'warning',
		// 	__( 'This event has already past.', 'gatherpress' ),
		// 	{
		// 		id,
		// 		isDismissible: false,
		// 	}
		// );
	}
}

export function hasEventPast() {
	if (
		moment().valueOf() >
		// eslint-disable-next-line no-undef
		moment( GatherPress.event_datetime.datetime_end ).valueOf()
	) {
		return true;
	}

	return false;
}
