/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { EVENT_REST_API } from '../helpers/namespace';

/**
 * Store name for cached RSVP response counts.
 *
 * @since 0.36.0
 *
 * @type {string}
 */
export const RSVP_COUNTS_STORE = 'gatherpress/rsvp-counts';

const DEFAULT_STATE = {
	counts: {},
};

const actions = {
	receiveCounts( postId, counts ) {
		return {
			type: 'RECEIVE_RSVP_COUNTS',
			postId,
			counts,
		};
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'RECEIVE_RSVP_COUNTS':
			return {
				...state,
				counts: {
					...state.counts,
					[ action.postId ]: action.counts,
				},
			};
		default:
			return state;
	}
};

const selectors = {
	/**
	 * Response counts for one post, keyed by status.
	 *
	 * @since 0.36.0
	 *
	 * @param {Object} state  Store state.
	 * @param {number} postId The event post ID.
	 *
	 * @return {Object|null} Counts keyed by status, or null before they resolve.
	 */
	getCounts( state, postId ) {
		return state.counts[ postId ] ?? null;
	},

	/**
	 * Count for a single status on one post.
	 *
	 * @since 0.36.0
	 *
	 * @param {Object} state  Store state.
	 * @param {number} postId The event post ID.
	 * @param {string} status The response status key.
	 *
	 * @return {number|null} The count, or null before it resolves.
	 */
	getCount( state, postId, status ) {
		return state.counts[ postId ]?.[ status ]?.count ?? null;
	},
};

const resolvers = {
	// Both selectors resolve from the same request, so getCount delegates
	// rather than fetching the same endpoint once per status.
	*getCounts( postId ) {
		if ( ! postId ) {
			return;
		}

		try {
			const response = yield {
				type: 'FETCH_RSVP_COUNTS',
				postId,
			};

			yield actions.receiveCounts( postId, response?.data ?? {} );
		} catch {
			// An unreachable endpoint leaves the counts unresolved, which
			// callers render as an unsubstituted placeholder.
			yield actions.receiveCounts( postId, {} );
		}
	},

	getCount:
		( postId ) =>
			async ( { resolveSelect } ) => {
				await resolveSelect.getCounts( postId );
			},
};

const controls = {
	FETCH_RSVP_COUNTS( action ) {
		return apiFetch( {
			path: `${ EVENT_REST_API }/rsvp-responses?post_id=${ action.postId }`,
		} );
	},
};

export const store = createReduxStore( RSVP_COUNTS_STORE, {
	reducer,
	actions,
	selectors,
	resolvers,
	controls,
} );

register( store );
