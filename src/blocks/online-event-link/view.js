/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { initPostContext } from '../../helpers/interactivity';
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

			// Drop our own "opens in a new tab" warning carried over from a
			// previous link render. Keeping it through a swap would announce
			// the warning on plain text, and duplicate it once a link comes
			// back. A dedicated marker class is used so tooltip or
			// admin-list screen-reader spans are never touched.
			currentElement
				.querySelector( '.gatherpress-new-tab-notice' )
				?.remove();

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
				// Mirror the server-rendered markup in render.php: the
				// screen-reader warning must survive the client-side swap.
				// The string travels in the context payload because script
				// modules cannot import @wordpress/i18n.
				linkElement.innerHTML =
					currentHTML +
					`<span class="screen-reader-text gatherpress-new-tab-notice"> ${ context?.newTabWarning ?? '' }</span>`;
				currentElement.replaceWith( linkElement );
			} else if ( ! hasLink && isLink ) {
				const spanElement = document.createElement( 'span' );
				spanElement.className = 'gatherpress-online-event__text';
				spanElement.innerHTML = currentHTML;
				currentElement.replaceWith( spanElement );
			} else if ( hasLink && isLink ) {
				// Keep the warning when the reactive anchor stays an
				// anchor but the href changes; the notice was already
				// dropped above, so appending unconditionally is
				// idempotent.
				if ( currentElement.href !== onlineEventLink ) {
					currentElement.href = onlineEventLink;
				}

				if ( context?.newTabWarning ) {
					const sr = document.createElement( 'span' );
					sr.className = 'screen-reader-text gatherpress-new-tab-notice';
					sr.textContent = ' ' + context.newTabWarning;
					currentElement.appendChild( sr );
				}
			}
		},
	},
} );
