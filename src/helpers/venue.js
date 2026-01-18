/**
 * WordPress dependencies.
 */
import { select, useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { PT_EVENT, PT_VENUE, TAX_VENUE } from './namespace';

/**
 * Check if the current post type is a venue.
 *
 * This function determines whether the current post type in the WordPress editor
 * is associated with venue content.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is a venue; false otherwise.
 */
export function isVenuePostType() {
	return 'gatherpress_venue' === select( 'core/editor' )?.getCurrentPostType();
}

/**
 * Retrieves a 'gatherpress_venue' post object from a given '_gatherpress_venue' term ID.
 *
 * Uses the taxonomy term ID to find the corresponding term object,
 * strips any leading underscore from the slug, and fetches the related
 * Venue post whose slug matches the term. Returns the first matching post.
 *
 * @since 1.0.0
 *
 * @param {number|null} termId The ID of the '_gatherpress_venue' term. If null, no post is retrieved.
 * @return {Object[]|Array}     An array of matching Venue post objects, or an empty array if none is found.
 */
export function GetVenuePostFromTermId( termId ) {
	const { venuePost } = useSelect(
		( wpSelect ) => {
			if ( null === termId ) {
				return [];
			}
			// Get the term object from the '_gatherpress_venue' taxonomy.
			const venueTerm = wpSelect( 'core' ).getEntityRecord(
				'taxonomy',
				TAX_VENUE,
				termId
			);
			// If term object exists, strip any leading underscore from its slug.
			const venueSlug = venueTerm?.slug.replace( /^_/, '' );
			// Query for one Venue post with the matching slug.
			return {
				venuePost: wpSelect( 'core' ).getEntityRecords(
					'postType',
					PT_VENUE,
					{
						per_page: 1,
						slug: venueSlug,
					}
				),
			};
		},
		[ termId ]
	);

	return venuePost;
}

/**
 * Retrieves the '_gatherpress_venue' term object associated with a given 'gatherpress_venue' post ID.
 *
 * Looks up the Venue post by ID from the `core` data store, prefixes its slug with an underscore
 * (to match the related taxonomy term format), and retrieves the term object from the '_gatherpress_venue' taxonomy.
 *
 * @since 1.0.0
 *
 * @param {number|null} postId The ID of the GatherPress venue post.
 *                             Defaults to null, in which case no term is retrieved.
 * @return {Object[]|Array}    An array of matching term objects, or an empty array if no matching term is found.
 */
export function GetVenueTermFromPostId( postId = null ) {
	const { venueTerm } = useSelect(
		( wpSelect ) => {
			if ( null === postId ) {
				return [];
			}
			// Retrieve the venue post entity from the WordPress data store.
			const venuePost = wpSelect( 'core' ).getEntityRecord(
				'postType',
				PT_VENUE,
				postId
			);
			// Prefix the slug with an underscore to match taxonomy term format.
			const venueSlug = '_' + venuePost.slug;
			// Fetch the '_gatherpress_venue' taxonomy term matching this slug.
			return {
				venueTerm: wpSelect( 'core' ).getEntityRecords(
					'taxonomy',
					TAX_VENUE,
					{
						per_page: 1,
						slug: venueSlug,
					}
				),
			};
		},
		[ postId ]
	);

	return venueTerm;
}

/**
 * Retrieves a 'gatherpress_venue' post object from a given 'gatherpress_event' post ID.
 *
 * Uses the WordPress data store to get the Event post entity from the REST API,
 * extracts the related Venue term ID from its `_gatherpress_venue` taxonomy field,
 * and fetches the corresponding Venue post via `GetVenuePostFromTermId()`.
 *
 * @since 1.0.0
 *
 * @param {number} eventId The ID of the GatherPress event post.
 * @return {Object|null} The related Venue post object, or null if none is found.
 */
export function GetVenuePostFromEventId( eventId ) {
	const { termId } = useSelect(
		( wpSelect ) => {
			// Retrieve the event post entity from the core store.
			const eventPost = wpSelect( 'core' ).getEntityRecord(
				'postType',
				PT_EVENT,
				eventId
			);
			// Extract the venue term ID if available; otherwise return null.
			return {
				termId:
					eventPost && 1 <= eventPost._gatherpress_venue.length
						? eventPost?._gatherpress_venue?.[ 0 ]
						: null,
			};
		},
		[ eventId ]
	);

	// Fetch and return the related Venue post using the term ID.
	return GetVenuePostFromTermId( termId );
}

const getVenueTitle = ( venue, kind ) => {
	switch ( kind ) {
		case 'taxonomy':
			return venue.name;
		case 'postType':
			return venue.title.rendered;
		default:
			return '&hellip;loading';
	}
};

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
	name = TAX_VENUE
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
			const fetchedVenues = ( venues ?? [] ).map( ( venueObj ) => {
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

			// Ensure the current venue is included in the list.
			if ( 0 > foundVenue && venue ) {
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
