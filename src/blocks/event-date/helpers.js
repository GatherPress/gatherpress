/**
 * Internal dependencies
 */
import { findEventPostById } from '../../helpers/event';

/**
 * Resolves the event datetime data for the event-date block editor.
 *
 * Extracted from the useSelect callback in edit.js so the routing logic can be
 * unit-tested independently of React rendering. Called once per render inside
 * the component's useSelect with the reactive `select` function.
 *
 * @since 0.34.0
 *
 * @param {Function}         select              WordPress data select function.
 * @param {string|null}      contextPostType     Block context postType value.
 * @param {number|undefined} contextQueryId      Block context queryId value (set by core/query).
 * @param {number}           postId              Resolved post ID (attribute override or context).
 * @param {boolean}          hasExplicitOverride Whether postId comes from block attributes.
 *
 * @return {Object} Datetime data: dateTimeStart, dateTimeEnd, timezone, isValidEvent, isLoading.
 */
export function resolveEventDateData( select, contextPostType, contextQueryId, postId, hasExplicitOverride ) {
	const postType =
		contextPostType ??
		select( 'core/editor' )?.getCurrentPostType();

	const supportsEventDate = !! select( 'core' )
		.getPostType( postType )?.supports?.[ 'gatherpress-event-date' ];

	// Check the editor document type reactively, distinct from the block
	// context post type. In the site editor the document type is wp_template,
	// so this guard is false even when contextPostType is gatherpress_event.
	const editorPostType = select( 'core/editor' )?.getCurrentPostType();
	const isDirectEditingEvent = !! select( 'core' )
		.getPostType( editorPostType )?.supports?.[ 'gatherpress-event-date' ];

	if ( ! postId ) {
		return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isLoading: false, isValidEvent: false };
	}

	// When editing an event directly (not inside a query loop), use the
	// datetime store for live updates. Three conditions must hold:
	// - isDirectEditingEvent: the editor document is itself an event.
	// - undefined === contextQueryId: core/query sets queryId on every block in
	//   the loop template, so a defined value means this instance represents a
	//   different queried post and must fetch its own dates via the entity
	//   record below.
	// - postId === current post id: a gatherpress/venue block overrides the
	//   postId/postType context for its inner blocks (e.g. sourcePostType
	//   "gatherpress_production"), so an event-date block nested there points at
	//   a different post than the one being edited. The live store only holds
	//   the edited event's dates, so it is only correct when the resolved postId
	//   is that edited post (#1794).
	if (
		isDirectEditingEvent &&
		undefined === contextQueryId &&
		postId === select( 'core/editor' )?.getCurrentPostId()
	) {
		const datetimeStore = select( 'gatherpress/datetime' );
		return {
			dateTimeStart: datetimeStore.getDateTimeStart(),
			dateTimeEnd: datetimeStore.getDateTimeEnd(),
			timezone: datetimeStore.getTimezone(),
			isLoading: false,
			isValidEvent: true,
		};
	}

	// postId override on a host that itself doesn't support event-date
	// (e.g. a regular page or template part). Resolve the override target
	// across event-supporting post types so the block can light up.
	if ( hasExplicitOverride && ! supportsEventDate ) {
		const overridePost = findEventPostById( select, postId );
		if ( ! overridePost ) {
			return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isLoading: false, isValidEvent: false };
		}
		const overrideMeta = overridePost.meta;
		return {
			dateTimeStart: overrideMeta?.gatherpress_datetime_start,
			dateTimeEnd: overrideMeta?.gatherpress_datetime_end,
			timezone: overrideMeta?.gatherpress_timezone,
			isLoading: false,
			isValidEvent: true,
		};
	}

	// Short-circuit before checking resolution: if the context post type
	// doesn't support event-date we never call getEntityRecord, so its
	// resolver never fires and hasFinishedResolution would stay false forever.
	if ( ! supportsEventDate ) {
		return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isLoading: false, isValidEvent: false };
	}

	// For Query Loop and override contexts, fetch from entity record.
	const hasResolved = select( 'core' ).hasFinishedResolution(
		'getEntityRecord',
		[ 'postType', postType, postId ]
	);

	if ( ! hasResolved ) {
		return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isLoading: true, isValidEvent: false };
	}

	const post = select( 'core' ).getEntityRecord( 'postType', postType, postId );
	const meta = post?.meta;

	return {
		dateTimeStart: meta?.gatherpress_datetime_start,
		dateTimeEnd: meta?.gatherpress_datetime_end,
		timezone: meta?.gatherpress_timezone,
		isLoading: false,
		isValidEvent: !! post && 'publish' === post?.status,
	};
}
