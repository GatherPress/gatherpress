/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { dispatch, select, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { createMomentWithTimezone, getTimezone } from './datetime';
import { getVenueTaxonomy, getVenuePostType } from './venue';

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
 * Checks if a post type has a given GatherPress post type support.
 *
 * If a postType argument is provided, checks against that value.
 * Otherwise, queries the current post type using the `select` function from the `core/editor` package.
 * Uses the WordPress data store to check post type supports.
 *
 * @since 1.0.0
 *
 * @param {string}      support  The post type support to check (e.g. 'gatherpress-event-date').
 * @param {string|null} postType Optional post type to check. If not provided, checks current editor post type.
 * @return {boolean} True if the post type has the given support, false otherwise.
 */
export function isPostTypeSupporting( support, postType = null ) {
	const typeToCheck =
		postType ?? select( 'core/editor' )?.getCurrentPostType();

	if ( ! typeToCheck ) {
		return false;
	}

	const postTypeObject = select( 'core' ).getPostType( typeToCheck );

	return !! postTypeObject?.supports?.[ support ];
}

/**
 * Reactive variant of `isPostTypeSupporting` for use in React components.
 *
 * `isPostTypeSupporting` reads `getPostType()` non-reactively, so when the
 * post-type definition isn't yet cached at render time the support gate
 * resolves to `false` and the component never re-renders once it loads.
 * This hook subscribes via `useSelect` so the component re-renders the moment
 * the supports become known — which is the difference between a permanently
 * dimmed block in a Query Loop and one that lights up correctly.
 *
 * @since 1.0.0
 *
 * @param {string}      support  The post type support to check.
 * @param {string|null} postType Optional post type to check. Falls back to the editor post type.
 * @return {boolean} True if the resolved post type has the given support, false otherwise.
 */
export function usePostTypeSupports( support, postType = null ) {
	return useSelect(
		( wpSelect ) => {
			const typeToCheck =
				postType ?? wpSelect( 'core/editor' )?.getCurrentPostType();

			if ( ! typeToCheck ) {
				return false;
			}

			return !! wpSelect( 'core' ).getPostType( typeToCheck )
				?.supports?.[ support ];
		},
		[ support, postType ]
	);
}

/**
 * Checks if a post type supports event_date in the GatherPress application.
 *
 * @since 1.0.0
 *
 * @param {string|null} postType Optional post type to check. If not provided, checks current editor post type.
 * @return {boolean} True if the post type supports event_date, false otherwise.
 */
export function isEventPostType( postType = null ) {
	return isPostTypeSupporting( 'gatherpress-event-date', postType );
}

/**
 * Look up a post by ID across all event-supporting post types.
 *
 * Used for postIdOverride scenarios where the editor host is not itself an
 * event-supporting post type (e.g. a regular page or template part) but the
 * user has pointed an event block at a specific event post via the advanced
 * Post ID Override control. Without this cross-type lookup, callers would
 * pass the host post type to `getEntityRecord`, get back `null`, and the
 * block would stay dimmed even though the override target is a real event.
 *
 * Returns `null` when the post type registry has not finished loading. The
 * caller's `useSelect` will re-run once it does, since `getPostTypes` is a
 * subscribed read.
 *
 * @since 1.0.0
 *
 * @param {Function} selectFunc WordPress data `select` function.
 * @param {number}   postId     Post ID to resolve.
 * @return {Object|null} The post entity if found in any event-supporting post
 *                       type; null when the registry isn't loaded yet, when
 *                       no event-supporting type owns the ID, or when the
 *                       found post isn't published.
 */
export function findEventPostById( selectFunc, postId ) {
	if ( ! postId ) {
		return null;
	}

	// `context: 'edit'` is required because WP REST only exposes the
	// `supports` field on post types in the edit context. Without it the
	// loop below never matches any type and the override silently fails.
	const postTypes = selectFunc( 'core' ).getPostTypes?.( {
		per_page: -1,
		context: 'edit',
	} );
	if ( ! Array.isArray( postTypes ) ) {
		return null;
	}

	for ( const type of postTypes ) {
		if ( ! type?.supports?.[ 'gatherpress-event-date' ] ) {
			continue;
		}
		// Query by `include` filter rather than `getEntityRecord( id )` so a
		// miss returns an empty array (HTTP 200) instead of a 404. The 404s
		// are technically accurate but they show up in browser devtools and
		// look like a real bug to anyone reading the console. Edit context
		// matches the default `getEntityRecord` uses inside the editor and
		// guarantees full `meta` in the response — callers like the
		// event-date block read `post.meta.gatherpress_datetime_start`.
		//
		// The `Event_Query` REST filter detects the `include` param and
		// skips its upcoming/past date filter so this lookup catches past
		// events too (see `Event_Query::rest_query`).
		const records = selectFunc( 'core' ).getEntityRecords(
			'postType',
			type.slug,
			{ include: [ postId ], context: 'edit', per_page: 1 }
		);
		if ( Array.isArray( records ) && 0 < records.length ) {
			const post = records[ 0 ];
			if ( post && 'publish' === post.status ) {
				return post;
			}
		}
	}

	return null;
}

/**
 * Checks if a block has a valid event ID (either from current post or postId override).
 *
 * This function checks if the block is connected to a valid event, either by being
 * placed in an event post or having a postId attribute that points to a valid event.
 *
 * Pass `useSelect`'s `select` callback as the first argument to subscribe the
 * caller to the underlying entity-record reads — without this, the gate is
 * computed once with whatever data was cached at first render and never
 * re-evaluates when the override target loads, leaving the block dimmed even
 * after the data arrives. The non-`useSelect` global `select` import is used
 * as a default to keep older call sites working, but new callers should pass
 * their `useSelect` callback's `select`.
 *
 * @since 1.0.0
 *
 * @param {Function|number|null} selectFuncOrPostId Either a `useSelect` `select`
 *                                                  callback (preferred) or, for
 *                                                  back-compat, a postId number
 *                                                  / null. When a function is
 *                                                  provided, the next argument
 *                                                  is treated as `postId`.
 * @param {number|null}          maybePostId        Post ID override to check
 *                                                  (when `selectFuncOrPostId`
 *                                                  is a function).
 * @param {string|null}          maybePostType      Optional post type to verify
 *                                                  before making API calls.
 * @return {boolean} True if connected to a valid event, false otherwise.
 */
export function hasValidEventId( selectFuncOrPostId = null, maybePostId = null, maybePostType = null ) {
	// Back-compat shim: if the first argument isn't a function, assume the
	// older `hasValidEventId( postId, postType )` shape and fall back to the
	// non-reactive global `select`. Calls inside `useSelect` should pass that
	// hook's `select` callback as the first argument so subscriptions track.
	let selectFunc;
	let postId;
	let postType;
	if ( 'function' === typeof selectFuncOrPostId ) {
		selectFunc = selectFuncOrPostId;
		postId = maybePostId;
		postType = maybePostType;
	} else {
		selectFunc = select;
		postId = selectFuncOrPostId;
		postType = maybePostId;
	}

	// If postId is provided, verify it points to a valid, published event.
	if ( postId ) {
		// Check if this is the current post being edited in the editor.
		const currentPostId =
			selectFunc( 'core/editor' )?.getCurrentPostId();
		const currentPostType =
			selectFunc( 'core/editor' )?.getCurrentPostType();
		const isCurrentPost =
			currentPostId && currentPostId === postId;

		const isEventSupporting = ( slug ) =>
			!! selectFunc( 'core' ).getPostType( slug )?.supports?.[
				'gatherpress-event-date'
			];

		// If this is the current post, check if it supports event_date.
		if ( isCurrentPost ) {
			if ( ! isEventSupporting( currentPostType ) ) {
				return false;
			}
			const post = selectFunc( 'core' ).getEntityRecord(
				'postType',
				currentPostType,
				postId
			);
			return !! post;
		}

		// Resolve the post type to look up the override target with. Order:
		// explicit hint (if event-supporting) → current editor type (if
		// event-supporting) → cross-type registry scan.
		let lookupType = null;
		if ( postType && isEventSupporting( postType ) ) {
			lookupType = postType;
		} else if ( isEventSupporting( currentPostType ) ) {
			lookupType = currentPostType;
		}

		if ( lookupType ) {
			const post = selectFunc( 'core' ).getEntityRecord(
				'postType',
				lookupType,
				postId
			);
			return !! post && 'publish' === post.status;
		}

		// Neither the hint nor the host is event-supporting. This is a
		// postIdOverride flow on a non-event host (e.g. a regular page). Scan
		// event-supporting post types so the block can still light up.
		return null !== findEventPostById( selectFunc, postId );
	}

	// Otherwise, check if current post supports event_date (no publish check needed).
	const editorPostType = selectFunc( 'core/editor' )?.getCurrentPostType();
	return !! selectFunc( 'core' ).getPostType( editorPostType )?.supports?.[
		'gatherpress-event-date'
	];
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
		select( 'gatherpress/datetime' )?.getDateTimeEnd?.() ?? '',
		timezone,
	);

	// Get current time in the event timezone.
	const now = createMomentWithTimezone(
		moment().format( 'YYYY-MM-DD HH:mm:ss' ),
		timezone,
	);

	return (
		isEventPostType() && now.valueOf() > dateTimeEnd.valueOf()
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
 * Checks if an event has the online-event term.
 *
 * This function checks if the event (either current post or specified by postId)
 * has the online-event venue term assigned.
 *
 * @since 1.0.0
 *
 * @param {number|null} postId Optional post ID override to check.
 * @return {boolean} True if the event has the online-event term, false otherwise.
 */
export function hasOnlineEventTerm( postId = null ) {
	// Derive the venue taxonomy from the current editor post type.
	const currentPostType = select( 'core/editor' )?.getCurrentPostType?.();
	const venueTaxonomy = getVenueTaxonomy( getVenuePostType( currentPostType ) );

	// Get the online-event term ID.
	const onlineEventTerms = select( 'core' ).getEntityRecords(
		'taxonomy',
		venueTaxonomy,
		{ slug: 'online-event', per_page: 1 }
	);
	const onlineEventTermId = onlineEventTerms?.[ 0 ]?.id;

	if ( ! onlineEventTermId ) {
		return false;
	}

	// If postId is provided, check that specific post.
	if ( postId ) {
		const post = select( 'core' ).getEntityRecord(
			'postType',
			currentPostType || 'gatherpress_event',
			postId
		);
		const venueTaxonomyIds = post?.[ venueTaxonomy ];

		if ( ! venueTaxonomyIds?.length ) {
			return false;
		}

		return venueTaxonomyIds.some(
			( id ) => String( id ) === String( onlineEventTermId )
		);
	}

	// Otherwise, check current post if it's an event.
	if ( ! isEventPostType() ) {
		return false;
	}

	const venueTaxonomyIds =
		select( 'core/editor' ).getEditedPostAttribute( venueTaxonomy );

	if ( ! venueTaxonomyIds?.length ) {
		return false;
	}

	return venueTaxonomyIds.some(
		( id ) => String( id ) === String( onlineEventTermId )
	);
}

/**
 * Determines whether the current RSVP mode is a per-event mode.
 *
 * @param {string} rsvpMode The current RSVP mode setting.
 *
 * @return {boolean} True if the mode is per_event_on or per_event_off.
 */
export function isPerEventRsvpMode( rsvpMode ) {
	return 'per_event_on' === rsvpMode || 'per_event_off' === rsvpMode;
}

/**
 * Determines whether RSVP is enabled for a specific event.
 *
 * In per-event modes, RSVP is enabled only if the event's enableRsvp flag is true.
 * In all_on mode RSVP is always enabled. In disabled mode it is never enabled.
 *
 * @param {string}  rsvpMode   The current RSVP mode setting.
 * @param {boolean} enableRsvp Whether RSVP is enabled for this specific event.
 *
 * @return {boolean} True if RSVP is enabled for this event, false otherwise.
 */
export function isRsvpEnabledForEvent( rsvpMode, enableRsvp ) {
	return (
		'disabled' !== rsvpMode &&
		( ! isPerEventRsvpMode( rsvpMode ) || enableRsvp )
	);
}

/**
 * Determines whether Open RSVP (email/token, non-logged-in) is enabled sitewide.
 *
 * @param {boolean} enableOpenRsvp The sitewide Open RSVP setting value.
 *
 * @return {boolean} True if Open RSVP is enabled sitewide, false otherwise.
 */
export function isOpenRsvpEnabled( enableOpenRsvp ) {
	return true === enableOpenRsvp;
}

/**
 * Gets event meta data (max guest limit, RSVP enabled flag, and anonymous RSVP setting).
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
 * @return {Object} Object containing maxGuestLimit, enableRsvp, and enableAnonymousRsvp.
 */
export function getEventMeta( selectFunc, postId, attributes ) {
	let maxLimit;
	let enableRsvp;
	let enableAnonymous;

	// Check if there's an explicit postId override in attributes.
	// If attributes.postId exists, it's an override - use entity record.
	// If postId only comes from context, use editor for live edits.
	const hasExplicitOverride = !! attributes?.postId;

	if ( hasExplicitOverride && postId ) {
		// Explicit override - fetch from post via core data store.
		const post = selectFunc( 'core' ).getEntityRecord( 'postType', 'gatherpress_event', postId );
		maxLimit = post?.meta?.gatherpress_max_guest_limit;
		// Stored as integer (0/1); undefined means not yet set, default to enabled.
		enableRsvp = 0 !== post?.meta?.gatherpress_enable_rsvp;
		enableAnonymous = Boolean( post?.meta?.gatherpress_enable_anonymous_rsvp );
	} else {
		// No override - check if current post is an event and use editor for live edits.
		const currentPostType = selectFunc( 'core/editor' )?.getCurrentPostType();
		const isCurrentPostEvent = isEventPostType( currentPostType );

		if ( isCurrentPostEvent ) {
			const meta = selectFunc( 'core/editor' ).getEditedPostAttribute( 'meta' );
			maxLimit = meta?.gatherpress_max_guest_limit;
			// Stored as integer (0/1); undefined means not yet set, default to enabled.
			enableRsvp = 0 !== meta?.gatherpress_enable_rsvp;
			enableAnonymous = Boolean( meta?.gatherpress_enable_anonymous_rsvp );
		}
	}

	return {
		maxGuestLimit: maxLimit ?? 0,
		enableRsvp: enableRsvp ?? true,
		enableAnonymousRsvp: enableAnonymous ?? false,
	};
}

