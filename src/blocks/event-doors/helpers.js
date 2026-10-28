/**
 * External dependencies
 */
import moment from 'moment';

/**
 * Internal dependencies
 */
import { convertPHPToMomentFormat } from '../../helpers/datetime';
import { findEventPostById } from '../../helpers/event';
import { getFromSettings } from '../../helpers/editor-settings';

/**
 * Resolves the doors open and close times for the event-doors block editor.
 *
 * The edited post reads its unsaved meta so the preview follows the fields as
 * they change. A post ID override reads the event it points at, and a block
 * in a Query Loop reads the queried post.
 *
 * @since TBD
 *
 * @param {Function}    select              WordPress data select function.
 * @param {string|null} contextPostType     Block context postType value.
 * @param {number|null} postId              Resolved post ID (attribute override or context).
 * @param {boolean}     hasExplicitOverride Whether postId comes from block attributes.
 *
 * @return {Object} The doorsOpen and doorsClose times, '' when unset.
 */
export function resolveDoorsTimes( select, contextPostType, postId, hasExplicitOverride ) {
	let meta;

	if ( postId && postId === select( 'core/editor' )?.getCurrentPostId() ) {
		meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
	} else if ( hasExplicitOverride ) {
		meta = findEventPostById( select, postId )?.meta;
	} else if ( postId && contextPostType ) {
		meta = select( 'core' ).getEntityRecord( 'postType', contextPostType, postId )?.meta;
	}

	return {
		doorsOpen: meta?.gatherpress_doors_open || '',
		doorsClose: meta?.gatherpress_doors_close || '',
	};
}

/**
 * Formats an 'H:i' doors time with the site's time format.
 *
 * @since TBD
 *
 * @param {string} time The time, such as '18:30'.
 *
 * @return {string} The formatted time.
 */
export function formatDoorsTime( time ) {
	return moment( time, 'HH:mm' ).format(
		convertPHPToMomentFormat( getFromSettings( 'timeFormat' ) ),
	);
}
