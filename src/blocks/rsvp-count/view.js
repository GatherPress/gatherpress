/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext } from '../../helpers/interactivity';

const { state } = store( 'gatherpress', {
	state: {
		posts: {},
	},
	callbacks: {
		/**
		 * Initializes the RSVP count state from data attributes.
		 *
		 * Reads the counts from the data-counts attribute and initializes
		 * the state for this post ID. This runs once on page load.
		 */
		initRsvpCount() {
			const context = getContext();
			const postId = context?.postId || 0;
			const element = getElement();

			if ( ! postId || ! element?.ref ) {
				return;
			}

			initPostContext( state, postId );

			const counts = element.ref.dataset.counts
				? JSON.parse( element.ref.dataset.counts )
				: null;

			// Delete attribute after reading to prevent re-initialization.
			delete element.ref.dataset.counts;

			if ( counts ) {
				state.posts[ postId ] = {
					...state.posts[ postId ],
					eventResponses: {
						attending: counts?.attending || 0,
						waitingList: counts?.waiting_list || 0,
						notAttending: counts?.not_attending || 0,
					},
				};
			}
		},
		/**
		 * Updates the RSVP count display when eventResponses changes.
		 *
		 * Watches the state for changes to event responses and updates
		 * the displayed count accordingly.
		 */
		updateRsvpCount() {
			const context = getContext();
			const postId = context?.postId || 0;
			const element = getElement();

			if ( ! postId || ! element?.ref ) {
				return;
			}

			// Access eventResponses to set up reactive dependency.
			const eventResponses = state.posts?.[ postId ]?.eventResponses;

			if ( ! eventResponses ) {
				return;
			}

			const statusKey = element.ref.dataset.status || 'attending';
			const singularLabel =
				element.ref.dataset.singularLabel || '%d attendee';
			const pluralLabel =
				element.ref.dataset.pluralLabel || '%d attendees';

			const count = eventResponses[ statusKey ] || 0;

			// Determine label based on count.
			const label = 1 === count ? singularLabel : pluralLabel;

			// Update the text content.
			const textElement = element.ref.querySelector(
				'.gatherpress-rsvp-count__text',
			);

			if ( textElement ) {
				textElement.textContent = label.replace( '%d', count );
			}
		},
	},
} );
