/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { createMomentWithTimezone } from '../../helpers/datetime';
import { findEventPostById } from '../../helpers/event';
import { getViewerTimeLabel } from './viewer-time';

/**
 * An event's operational status slug, or an empty string when there is none.
 *
 * @since TBD
 *
 * @param {*} value The `gatherpress_status` value from the REST field.
 *
 * @return {string} The status slug.
 */
function toStatus( value ) {
	return 'string' === typeof value ? value : '';
}

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
 * @return {Object} Datetime data: dateTimeStart, dateTimeEnd, timezone, status, isValidEvent, isLoading.
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
		return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isAllDay: false, timezonePreference: '', status: '', isLoading: false, isValidEvent: false };
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
			// From the edited post's meta so the preview follows the toggle
			// as it is flipped, rather than after a save.
			isAllDay: Boolean(
				select( 'core/editor' )?.getEditedPostAttribute?.( 'meta' )
					?.gatherpress_is_all_day,
			),
			timezonePreference:
				select( 'core/editor' )?.getEditedPostAttribute?.( 'meta' )
					?.gatherpress_show_timezone || '',
			// The edited status, so the preview follows the Event status
			// control before a save.
			status: toStatus(
				select( 'core/editor' )?.getEditedPostAttribute?.( 'gatherpress_status' ),
			),
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
			return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isAllDay: false, timezonePreference: '', status: '', isLoading: false, isValidEvent: false };
		}
		const overrideMeta = overridePost.meta;
		return {
			dateTimeStart: overrideMeta?.gatherpress_datetime_start,
			dateTimeEnd: overrideMeta?.gatherpress_datetime_end,
			timezone: overrideMeta?.gatherpress_timezone,
			isAllDay: Boolean( overrideMeta?.gatherpress_is_all_day ),
			timezonePreference: overrideMeta?.gatherpress_show_timezone || '',
			status: toStatus( overridePost.gatherpress_status ),
			isLoading: false,
			isValidEvent: true,
		};
	}

	// Short-circuit before checking resolution: if the context post type
	// doesn't support event-date we never call getEntityRecord, so its
	// resolver never fires and hasFinishedResolution would stay false forever.
	if ( ! supportsEventDate ) {
		return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isAllDay: false, timezonePreference: '', status: '', isLoading: false, isValidEvent: false };
	}

	// For Query Loop and override contexts, fetch from entity record.
	const hasResolved = select( 'core' ).hasFinishedResolution(
		'getEntityRecord',
		[ 'postType', postType, postId ],
	);

	if ( ! hasResolved ) {
		return { dateTimeStart: undefined, dateTimeEnd: undefined, timezone: undefined, isAllDay: false, timezonePreference: '', status: '', isLoading: true, isValidEvent: false };
	}

	const post = select( 'core' ).getEntityRecord( 'postType', postType, postId );
	const meta = post?.meta;

	return {
		dateTimeStart: meta?.gatherpress_datetime_start,
		dateTimeEnd: meta?.gatherpress_datetime_end,
		timezone: meta?.gatherpress_timezone,
		isAllDay: Boolean( meta?.gatherpress_is_all_day ),
		timezonePreference: meta?.gatherpress_show_timezone || '',
		status: toStatus( post?.gatherpress_status ),
		isLoading: false,
		isValidEvent: !! post && 'publish' === post?.status,
	};
}

/**
 * Whether the block appends the timezone, so whichever time it shows is labeled.
 *
 * The event's own preference wins, then the block's "Append time zone", then the
 * site setting. Mirrors `Html_Renderer::is_timezone_shown()`.
 *
 * @since TBD
 *
 * @param {string}  timezonePreference  The event's preference: 'always', 'never' or ''.
 * @param {string}  showTimezone        The block attribute: 'yes', 'no' or ''.
 * @param {boolean} defaultShowTimezone The site's "Show Timezone" setting.
 *
 * @return {boolean} True when the timezone is shown.
 */
export function isTimezoneAppended( timezonePreference, showTimezone, defaultShowTimezone ) {
	if ( 'always' === timezonePreference || 'never' === timezonePreference ) {
		return 'always' === timezonePreference;
	}

	return showTimezone ? 'yes' === showTimezone : Boolean( defaultShowTimezone );
}

/**
 * The help text of the "Show viewer local time" toggle.
 *
 * @since TBD
 *
 * @param {Object}  args                    Toggle state.
 * @param {boolean} args.isAllDay           Whether the event is all day.
 * @param {boolean} args.isTimezoneAppended Whether the block appends the timezone.
 *
 * @return {string} The help text.
 */
export function getViewerTimeHelp( { isAllDay, isTimezoneAppended: timezoneAppended } ) {
	if ( isAllDay ) {
		return __( 'All-day events do not show viewer local time.', 'gatherpress' );
	}

	if ( ! timezoneAppended ) {
		return __( 'Time zone must be appended to show viewer local time.', 'gatherpress' );
	}

	return __(
		'Displays the event time converted to each viewer\u2019s local timezone in a tooltip. Viewers in the same timezone see nothing extra.',
		'gatherpress',
	);
}

/**
 * The viewer time label the editor previews, the same one the frontend shows.
 *
 * Empty when the author is already in the event's timezone, as it is for a
 * viewer there.
 *
 * @since TBD
 *
 * @param {Object}      args           Label inputs.
 * @param {boolean}     args.isEnabled Whether the block shows the viewer time.
 * @param {string|null} args.start     The start the block shows, in the event timezone, or null.
 * @param {string|null} args.end       The end the block shows, in the event timezone, or null.
 * @param {string}      args.timezone  The event timezone.
 * @param {string}      args.separator The block's separator.
 * @param {boolean}     args.isAllDay  Whether the event is all day.
 *
 * @return {string} The label, or an empty string when there is nothing to add.
 */
export function getViewerTimePreview( { isEnabled, start, end, timezone, separator, isAllDay } ) {
	if ( ! isEnabled || isAllDay ) {
		return '';
	}

	const toGmt = ( datetime ) =>
		datetime
			? createMomentWithTimezone( datetime, timezone ).utc().format( 'YYYY-MM-DD HH:mm:ss' )
			: '';

	return getViewerTimeLabel( {
		startGmt: toGmt( start ),
		endGmt: toGmt( end ),
		eventTimezone: timezone,
		rangeFormat: sprintf(
			/* translators: 1: start in the viewer's timezone, 2: separator, 3: end in the viewer's timezone. */
			__( '%1$s %2$s %3$s your time', 'gatherpress' ),
			'%1$s',
			separator || __( 'to', 'gatherpress' ),
			'%2$s',
		),
		/* translators: %s: event start in the viewer's timezone. */
		singleFormat: __( '%s your time', 'gatherpress' ),
	} );
}
