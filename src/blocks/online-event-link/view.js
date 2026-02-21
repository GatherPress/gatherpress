/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext } from '../../helpers/interactivity';
import { safeHTML } from '../../helpers/globals';

const { state } = store( 'gatherpress', {
	callbacks: {
		/**
		 * Update online event link based on RSVP API response.
		 *
		 * This callback monitors the onlineEventLink in state (updated by RSVP API)
		 * and swaps between a clickable link and plain text. The element uses
		 * class "gatherpress-online-event__text" whether it's a <span> or <a>.
		 *
		 * @since 1.0.0
		 *
		 * @return {void}
		 */
		updateOnlineEventLink() {
			const context = getContext();
			const postId = context?.postId || 0;

			if ( ! postId ) {
				return;
			}

			initPostContext( state, postId );

			const element = getElement();
			const currentElement = element.ref.querySelector( '.gatherpress-online-event__text' );

			if ( ! currentElement ) {
				return;
			}

			const isLink = 'A' === currentElement.tagName;

			// Initialize state from DOM on first run.
			if ( undefined === state.posts[ postId ].onlineEventLink ) {
				state.posts[ postId ].onlineEventLink = isLink ? currentElement.href : '';
				// Don't manipulate DOM on first run - PHP already rendered it correctly.
				return;
			}

			// Access state.posts[postId].onlineEventLink for reactivity.
			const onlineEventLink = state.posts[ postId ]?.onlineEventLink || '';
			const hasLink = '' !== onlineEventLink;

			// Preserve current inner HTML (including tooltip markup), sanitized for security.
			const currentHTML = safeHTML( currentElement.innerHTML );

			if ( hasLink && ! isLink ) {
				const linkElement = document.createElement( 'a' );
				linkElement.className = 'gatherpress-online-event__text';
				linkElement.href = onlineEventLink;
				linkElement.target = '_blank';
				linkElement.rel = 'noopener noreferrer';
				linkElement.innerHTML = currentHTML;
				currentElement.replaceWith( linkElement );
			} else if ( ! hasLink && isLink ) {
				const spanElement = document.createElement( 'span' );
				spanElement.className = 'gatherpress-online-event__text';
				spanElement.innerHTML = currentHTML;
				currentElement.replaceWith( spanElement );
			} else if ( hasLink && isLink && currentElement.href !== onlineEventLink ) {
				currentElement.href = onlineEventLink;
			}
		},
	},
} );
