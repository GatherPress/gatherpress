/**
 * WordPress dependencies
 */
import { select, useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */

/**
 * Default venue post type slug used as a fallback when no override is configured.
 *
 * @since 0.27.0
 *
 * @type {string}
 */
const DEFAULT_VENUE_POST_TYPE = 'gatherpress_venue';

/**
 * Returns the venue taxonomy slug for a given venue post type.
 *
 * The taxonomy is derived by prepending an underscore to the venue post type slug,
 * following the convention established in PHP via Venue\Setup::get_taxonomy().
 * For example, 'gatherpress_venue' becomes '_gatherpress_venue'.
 *
 * @since 0.27.0
 *
 * @param {string} [venuePostType='gatherpress_venue'] The venue post type slug.
 *
 * @return {string} The taxonomy slug for the given venue post type.
 */
export function getVenueTaxonomy( venuePostType = DEFAULT_VENUE_POST_TYPE ) {
	return '_' + venuePostType;
}

/**
 * Retrieves the venue post type slug for a given event post type.
 *
 * Reads the venuePostTypes map from the block editor settings exposed by PHP
 * via the block_editor_settings_all filter. Falls back to 'gatherpress_venue'
 * if the map is unavailable or the event post type is not found.
 *
 * @since 0.27.0
 *
 * @param {string} [eventPostType=''] The event post type slug to look up.
 *
 * @return {string} The venue post type slug for the given event post type.
 */
export function getVenuePostType( eventPostType = '' ) {
	const map =
		select( 'core/editor' )?.getEditorSettings?.()?.gatherpress
			?.config?.venuePostTypes ?? {};
	return map[ eventPostType ] ?? DEFAULT_VENUE_POST_TYPE;
}

/**
 * Check if the current post type is a venue.
 *
 * Uses the WordPress data store to check whether the current editor post type
 * declares the 'gatherpress-venue-information' support, which is the identifier
 * for all venue post types.
 *
 * @since 0.27.0
 *
 * @return {boolean} True if the current post type is a venue; false otherwise.
 */
export function isVenuePostType() {
	const postType = select( 'core/editor' )?.getCurrentPostType();
	return !! select( 'core' )
		?.getPostType( postType )
		?.supports?.[ 'gatherpress-venue-information' ];
}

/**
 * Retrieves a venue post object from a given '_gatherpress_venue' term ID.
 *
 * Uses the taxonomy term ID to find the corresponding term object,
 * strips any leading underscore from the slug, and fetches the related
 * venue post whose slug matches the term. Returns the first matching post.
 *
 * @since 0.27.0
 *
 * @param {number|null} termId        The ID of the '_gatherpress_venue' term. If null, no post is retrieved.
 * @param {string}      venuePostType The post type to query for the venue post. Defaults to 'gatherpress_venue'.
 *
 * @return {Object[]|Array}           An array of matching venue post objects, or an empty array if none is found.
 */
export function useVenuePostFromTermId( termId, venuePostType = DEFAULT_VENUE_POST_TYPE ) {
	const { venuePost } = useSelect(
		( wpSelect ) => {
			if ( null === termId ) {
				return { venuePost: undefined };
			}
			// Get the term object from the venue taxonomy derived from the venue post type.
			const venueTerm = wpSelect( 'core' ).getEntityRecord(
				'taxonomy',
				getVenueTaxonomy( venuePostType ),
				termId
			);
			// If term object exists, strip any leading underscore from its slug.
			const venueSlug = venueTerm?.slug?.replace( /^_/, '' );
			// In case the Venue post (source post type) does also support gatherpress-event-date,
			// we need to ensure that all posts of that type are queried,
			// not only upcoming ones, which would be the default.
			const isEventDateSupported = wpSelect( 'core' ).getPostType( venuePostType )
				?.supports?.[ 'gatherpress-event-date' ] ? 'all' : null;
			// Query for one venue post with the matching slug.
			return {
				venuePost: wpSelect( 'core' ).getEntityRecords(
					'postType',
					venuePostType,
					{
						per_page: 1,
						slug: venueSlug,
						gatherpress_event_query: isEventDateSupported,
					}
				),
			};
		},
		[ termId, venuePostType ]
	);

	return venuePost;
}

/**
 * Retrieves the venue taxonomy term object associated with a given venue post ID.
 *
 * Looks up the venue post by ID from the `core` data store, prefixes its slug with an underscore
 * (to match the related taxonomy term format), and retrieves the term object from the derived
 * venue taxonomy.
 *
 * @since 0.27.0
 *
 * @param {number|null} postId        The ID of the venue post. Defaults to null, in which case no term is retrieved.
 * @param {string}      venuePostType The venue post type slug. Defaults to 'gatherpress_venue'.
 *
 * @return {Object[]|Array}           An array of matching term objects, or an empty array if no matching term is found.
 */
export function useVenueTermFromPostId( postId = null, venuePostType = DEFAULT_VENUE_POST_TYPE ) {
	const { venueTerm } = useSelect(
		( wpSelect ) => {
			if ( null === postId ) {
				return { venueTerm: undefined };
			}
			// Retrieve the venue post entity from the WordPress data store.
			const venuePost = wpSelect( 'core' ).getEntityRecord(
				'postType',
				venuePostType,
				postId
			);
			// Bail when the post hasn't resolved yet (or arrived without a
			// slug) so the underscore-prefix doesn't produce `_undefined`.
			if ( ! venuePost?.slug ) {
				return { venueTerm: undefined };
			}
			// Prefix the slug with an underscore to match taxonomy term format.
			const venueSlug = '_' + venuePost.slug;
			// Fetch the venue taxonomy term matching this slug.
			return {
				venueTerm: wpSelect( 'core' ).getEntityRecords(
					'taxonomy',
					getVenueTaxonomy( venuePostType ),
					{
						per_page: 1,
						slug: venueSlug,
					}
				),
			};
		},
		[ postId, venuePostType ]
	);

	return venueTerm;
}

/**
 * Retrieves a venue post object from a given event post ID.
 *
 * Queries the venue taxonomy terms associated with the event post directly
 * via the REST API (using the `post` parameter on the terms endpoint), avoiding
 * a separate context=edit fetch of the event post itself. Falls back to the
 * first non-online-event term and fetches the corresponding venue post.
 *
 * @since 0.27.0
 *
 * @param {number} eventId  The ID of the event post.
 * @param {string} postType The post type of the event (defaults to the current editor post type).
 *
 * @return {Object|null} The related venue post object, or null if none is found.
 */
export function GetVenuePostFromEventId( eventId, postType = null ) {
	const { termId, venuePostType } = useSelect(
		( wpSelect ) => {
			// Resolve the post type: use provided value or fall back to the current editor post type.
			const resolvedPostType =
				postType || wpSelect( 'core/editor' )?.getCurrentPostType();

			if ( ! resolvedPostType || ! eventId ) {
				return { termId: null, venuePostType: DEFAULT_VENUE_POST_TYPE };
			}

			// Resolve the venue post type for this event post type from editor settings.
			const venuePostTypeMap =
				wpSelect( 'core/editor' )?.getEditorSettings?.()?.gatherpress
					?.config?.venuePostTypes ?? {};

			const resolvedVenuePostType =
				venuePostTypeMap[ resolvedPostType ] ?? DEFAULT_VENUE_POST_TYPE;

			// Query venue taxonomy terms associated with this event post directly.
			// This avoids fetching the event post with context=edit, which would fail
			// for custom post types in query loop contexts.
			const venueTax = getVenueTaxonomy( resolvedVenuePostType );
			const venueTerms = wpSelect( 'core' ).getEntityRecords(
				'taxonomy',
				venueTax,
				{ post: eventId, per_page: 10, context: 'view' }
			);

			// Find the first non-online-event term.
			const venueTerm = venueTerms?.find(
				( term ) => 'online-event' !== term.slug
			);

			return {
				termId: venueTerm?.id ?? null,
				venuePostType: resolvedVenuePostType,
			};
		},
		[ eventId, postType ]
	);

	// Fetch and return the related venue post using the term ID and resolved venue post type.
	return useVenuePostFromTermId( termId, venuePostType );
}

/**
 * Get the title of a venue based on its kind (taxonomy term or post type).
 *
 * @since 0.27.0
 *
 * @param {Object} venue Venue object (either a term or post).
 * @param {string} kind  Type of venue ('taxonomy' or 'postType').
 *
 * @return {string} The venue title.
 */
export function getVenueTitle( venue, kind ) {
	switch ( kind ) {
		case 'taxonomy':
			return venue.name;
		case 'postType':
			return venue.title.rendered;
		default:
			return '&hellip;loading';
	}
}

/**
 * Get a list of venue options from either posts or taxonomy terms.
 *
 * Adapted from useAuthorsQuery()
 * @see gutenberg/packages/editor/src/components/post-author/hook.js
 *
 * @param {string} search  Current search string for venue filtering.
 * @param {number} venueId Currently selected venue, can be either a post ID or a taxonomy term ID.
 * @param {string} kind    Actual kind to query for, could taxonomy (default) or posttype.
 * @param {string} name    Name of the current kind.
 *
 * @return {Array} A list options prepared for a typical combobox, with ID and label.
 */
export function useVenueOptions(
	search,
	venueId,
	kind = 'taxonomy',
	name = getVenueTaxonomy( DEFAULT_VENUE_POST_TYPE )
) {
	const { venue, venues } = useSelect(
		( wpSelect ) => {
			// Unified for VenueTermsCombobox and VenuePostsCombobox
			const { getEntityRecord, getEntityRecords } = wpSelect( coreStore );
			const query = {
				context: 'view',
				per_page: 10,
				search,
				orderby: 'id',
				order: 'desc',
			};

			return {
				// Query for the currently selected venue,
				// which may be a venue-term or a venue-post,
				// depending on context.
				venue: getEntityRecord( kind, name, venueId ),
				venues: getEntityRecords( kind, name, query ),
			};
		},
		[ kind, name, search, venueId ]
	);

	// Using useMemo will cause a re-render only when the raw venues really change.
	const venueOptions = useMemo(
		() => {
			// Create a combobox-friendly list as dropdown
			// from the array of venues (can be ~posts or ~terms).
			// Filter out the online-event term since it's controlled by a separate toggle.
			const fetchedVenues = ( venues ?? [] )
				.filter( ( venueObj ) => 'online-event' !== venueObj.slug )
				.map( ( venueObj ) => {
					return {
						value: venueObj.id,
						label: decodeEntities( getVenueTitle( venueObj, kind ) ),
					};
				} );

			// Check if the current venue is already included in the list.
			// Will be -1 if not found.
			const foundVenue = fetchedVenues.findIndex(
				( { value } ) => venue?.id === value
			);

			// Ensure the current venue is included in the list (but not online-event).
			if ( 0 > foundVenue && venue && 'online-event' !== venue.slug ) {
				return [
					{
						value: venue.id,
						label: decodeEntities( getVenueTitle( venue, kind ) ),
					},
					...fetchedVenues,
				];
			}

			return fetchedVenues;
		},
		// Dependency array, every time venue or venues is updated,
		//  the useMemo callback will be called.
		[ venue, venues, kind ]
	);

	return { venueOptions };
}

/**
 * Reads the venue taxonomy term IDs for a given post.
 *
 * Tries the editor in-memory state first (PHP preload data + pending edits via
 * getEditedPostAttribute), then falls back to a REST context=view query when
 * the in-memory state is not yet an array. This avoids triggering context=edit
 * REST requests that would fail for custom post types without edit permissions.
 *
 * Returns undefined when skipped or when postId is absent and the editor
 * attribute has not yet loaded.
 *
 * @since 0.27.0
 *
 * @param {string}      venueTaxonomy Taxonomy slug (e.g. '_gatherpress_venue').
 * @param {number|null} postId        The event post whose terms should be fetched.
 * @param {boolean}     skip          When true, skip all lookups and return undefined.
 *
 * @return {number[]|undefined} Array of term IDs, or undefined when not yet resolved.
 */
export function useVenueTaxonomyIds( venueTaxonomy, postId, skip = false ) {
	return useSelect(
		( wpSelect ) => {
			if ( skip ) {
				return undefined;
			}

			// Try editor in-memory state first (PHP preload data + pending edits).
			const editorAttr =
				wpSelect( 'core/editor' )?.getEditedPostAttribute( venueTaxonomy );
			if ( Array.isArray( editorAttr ) ) {
				return editorAttr;
			}

			if ( ! postId ) {
				return undefined;
			}

			// Fallback: query taxonomy terms with context=view (no edit permissions needed).
			const terms = wpSelect( 'core' ).getEntityRecords(
				'taxonomy',
				venueTaxonomy,
				{ post: postId, per_page: 100, context: 'view' }
			);
			return terms?.map( ( t ) => t.id );
		},
		[ skip, venueTaxonomy, postId ]
	);
}

/**
 * Hook to fetch the most popular venues (based on usage count).
 *
 * Retrieves venue taxonomy terms ordered by count (number of events using that venue)
 * in descending order, limited to the specified number.
 *
 * @since 0.27.0
 *
 * @param {number} limit         Maximum number of popular venues to fetch (default: 3).
 * @param {string} venuePostType Venue post type slug used to derive the taxonomy (default: 'gatherpress_venue').
 *
 * @return {Array} Array of popular venue terms with id, name, and count properties.
 */
export function usePopularVenues( limit = 3, venuePostType = DEFAULT_VENUE_POST_TYPE ) {
	const popularVenues = useSelect(
		( wpSelect ) => {
			const { getEntityRecords } = wpSelect( coreStore );
			// Fetch extra to account for filtering out online-event.
			const query = {
				context: 'view',
				per_page: limit + 1,
				orderby: 'count',
				order: 'desc',
				hide_empty: true, // Only show venues that are actually used.
			};

			const venues = getEntityRecords(
				'taxonomy',
				getVenueTaxonomy( venuePostType ),
				query
			);
			// Filter out the online-event term since it's controlled by a separate toggle.
			return venues
				?.filter( ( venue ) => 'online-event' !== venue.slug )
				.slice( 0, limit );
		},
		[ limit, venuePostType ]
	);

	return popularVenues ?? [];
}

/**
 * Look up a post by ID across all venue-supporting post types.
 *
 * Mirrors `findEventPostById()` but scans for `gatherpress-venue-information`
 * support instead of `gatherpress-event-date`. Used by the venue block's
 * `postIdOverride` resolver to detect when the override target is a venue
 * post (so it can be used directly) vs. an event post (so the venue is
 * derived from the event's venue taxonomy).
 *
 * Returns `null` when the post type registry has not finished loading. The
 * caller's `useSelect` will re-run once it does, since `getPostTypes` is a
 * subscribed read.
 *
 * @since 0.27.0
 *
 * @param {Function} selectFunc WordPress data `select` function.
 * @param {number}   postId     Post ID to resolve.
 *
 * @return {Object|null} The post entity if found in any venue-supporting post
 *                       type; null when the registry isn't loaded yet, when
 *                       no venue-supporting type owns the ID, or when the
 *                       found post isn't published.
 */
export function findVenuePostById( selectFunc, postId ) {
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
		if ( ! type?.supports?.[ 'gatherpress-venue-information' ] ) {
			continue;
		}
		// Query by `include` filter rather than `getEntityRecord( id )` so a
		// miss returns an empty array (HTTP 200) instead of a 404. The 404s
		// are technically accurate but they show up in browser devtools and
		// look like a real bug to anyone reading the console. Edit context
		// matches the default `getEntityRecord` uses inside the editor and
		// keeps the response shape consistent with the event-side helper.
		const records = selectFunc( 'core' ).getEntityRecords(
			'postType',
			type.slug,
			{ include: [ postId ], context: 'edit', per_page: 1 }
		);
		if ( Array.isArray( records ) && 0 < records.length ) {
			const post = records[ 0 ];
			if ( 'publish' === post?.status ) {
				return post;
			}
		}
	}

	return null;
}
