/**
 * Internal dependencies
 */
import { isEventPostType, findEventPostById } from '../../helpers/event';

/**
 * Resolves the event datetime data for the event-date block editor.
 *
 * Extracted from the useSelect callback in edit.js so the routing logic can be
 * unit-tested independently of React rendering. Called once per render inside
 * the component's useSelect with the reactive `select` function.
 *
 * @since 0.34.0
 *
 * @param {Function} select              WordPress data select function.
 * @param {Object}   context             Block context (postId, postType, queryId).
 * @param {number}   postId              Resolved post ID (attribute override or context).
 * @param {boolean}  hasExplicitOverride Whether postId comes from block attributes.
 *
 * @return {Object} Datetime data: dateTimeStart, dateTimeEnd, timezone, isValidEvent, isLoading.
 */
export function resolveEventDateData( select, context, postId, hasExplicitOverride ) {
	const postType =
		context?.postType ||
		select( 'core/editor' )?.getCurrentPostType();

	const supportsEventDate = !! select( 'core' )
		.getPostType( postType )?.supports?.[ 'gatherpress-event-date' ];

	if ( ! postId ) {
		return { isValidEvent: false };
	}

	// When editing an event directly (not inside a query loop), use the
	// datetime store for live updates.
	if ( isEventPostType() ) {
		const datetimeStore = select( 'gatherpress/datetime' );
		return {
			dateTimeStart: datetimeStore.getDateTimeStart(),
			dateTimeEnd: datetimeStore.getDateTimeEnd(),
			timezone: datetimeStore.getTimezone(),
			isValidEvent: supportsEventDate,
		};
	}

	// postId override on a host that itself doesn't support event-date
	// (e.g. a regular page or template part). Resolve the override target
	// across event-supporting post types so the block can light up.
	if ( hasExplicitOverride && ! supportsEventDate ) {
		const overridePost = findEventPostById( select, postId );
		if ( ! overridePost ) {
			return { isValidEvent: false };
		}
		const overrideMeta = overridePost?.meta;
		return {
			dateTimeStart: overrideMeta?.gatherpress_datetime_start,
			dateTimeEnd: overrideMeta?.gatherpress_datetime_end,
			timezone: overrideMeta?.gatherpress_timezone,
			isValidEvent: true,
		};
	}

	// Short-circuit before checking resolution: if the context post type
	// doesn't support event-date we never call getEntityRecord, so its
	// resolver never fires and hasFinishedResolution would stay false forever.
	if ( ! supportsEventDate ) {
		return { isValidEvent: false };
	}

	// For Query Loop and override contexts, fetch from entity record.
	const hasResolved = select( 'core' ).hasFinishedResolution(
		'getEntityRecord',
		[ 'postType', postType, postId ]
	);

	if ( ! hasResolved ) {
		return { isLoading: true, isValidEvent: false };
	}

	const post = select( 'core' ).getEntityRecord( 'postType', postType, postId );
	const meta = post?.meta;

	return {
		dateTimeStart: meta?.gatherpress_datetime_start,
		dateTimeEnd: meta?.gatherpress_datetime_end,
		timezone: meta?.gatherpress_timezone,
		isValidEvent: !! post && 'publish' === post?.status,
	};
}
