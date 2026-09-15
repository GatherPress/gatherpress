/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { getPostKey, initPostContext } from '../../helpers/interactivity';
import { stripScriptsAndEventHandlers } from '../../helpers/globals';

const { state } = store( 'gatherpress', {
	callbacks: {
		/**
		 * Update online event link based on RSVP API response.
		 *
		 * This callback monitors the onlineEventLink in state (updated by RSVP API)
		 * and swaps between a clickable link and plain text. The element uses
		 * class "gatherpress-online-event__text" whether it's a <span> or <a>.
		 *
		 * @since 0.34.0
		 *
		 * @return {void}
		 */
		updateOnlineEventLink() {
			const context = getContext();
			const postId = context?.postId || 0;

			if ( ! postId ) {
				return;
			}

			const postKey = getPostKey( postId, context?.recurrenceId );

			initPostContext( state, postKey );

			const element = getElement();
			const currentElement = element.ref.querySelector( '.gatherpress-online-event__text' );

			if ( ! currentElement ) {
				return;
			}

			const isLink = 'A' === currentElement.tagName;

			// Initialize state from DOM on first run.
			if ( undefined === state.posts[ postKey ].onlineEventLink ) {
				state.posts[ postKey ].onlineEventLink = isLink ? currentElement.href : '';
				// Don't manipulate DOM on first run - PHP already rendered it correctly.
				return;
			}

			// Access state.posts[postKey].onlineEventLink for reactivity.
			const onlineEventLink = state.posts[ postKey ]?.onlineEventLink || '';
			const hasLink = '' !== onlineEventLink;

			// Preserve the current inner HTML (including tooltip markup)
			// when we swap the wrapper between <a> and <span>. The HTML
			// originates from PHP `render.php`, which escapes properly,
			// so this is defense-in-depth against any third-party script
			// that may have mutated the DOM between server render and
			// our handler — not a substitute for proper escaping.
			const currentHTML = stripScriptsAndEventHandlers(
				currentElement.innerHTML
			);

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
