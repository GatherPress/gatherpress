/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { initPostContext } from '../../helpers/interactivity';
import { stripScriptsAndEventHandlers } from '../../helpers/globals';

/**
 * Turns every tooltip inside an element into a plain element.
 *
 * The link text can carry an "attendees only" tooltip, which explains why
 * there is no link. A working link must not keep that note in its name, so
 * this removes the tooltip attributes and classes, the screen-reader copy of
 * the note, and the tab stop the tooltip script adds. The text stays.
 *
 * @since TBD
 *
 * @param {HTMLElement} element The element that holds the link text.
 *
 * @return {void}
 */
export function removeTooltips( element ) {
	element.querySelectorAll( '.gatherpress-tooltip' ).forEach( ( tooltip ) => {
		tooltip
			.querySelectorAll( ':scope > .gatherpress-tooltip-notice' )
			.forEach( ( notice ) => notice.remove() );
		tooltip.classList.remove(
			'gatherpress-tooltip',
			'gatherpress-tooltip--is-active',
			'gatherpress-tooltip--is-dismissed',
		);

		// Match the server output, which drops an empty class attribute.
		if ( 0 === tooltip.classList.length ) {
			tooltip.removeAttribute( 'class' );
		}

		tooltip.removeAttribute( 'data-gatherpress-tooltip' );
		tooltip.removeAttribute( 'data-gatherpress-tooltip-text-color' );
		tooltip.removeAttribute( 'data-gatherpress-tooltip-bg-color' );
		tooltip.removeAttribute( 'tabindex' );
	} );
}

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

			// Start from the full link text in the context (tooltip markup
			// included), because the server removes the tooltip from a
			// working link and the plain text must get it back. Fall back to
			// the current inner HTML. Both come from PHP `render.php`, which
			// escapes properly, so the strip is defense-in-depth against any
			// third-party script that may have mutated the DOM between
			// server render and our handler — not a substitute for proper
			// escaping.
			const currentHTML = stripScriptsAndEventHandlers(
				context.linkText || currentElement.innerHTML,
			);

			if ( hasLink && ! isLink ) {
				const linkElement = document.createElement( 'a' );
				linkElement.className = 'gatherpress-online-event__text';
				linkElement.href = onlineEventLink;
				linkElement.target = '_blank';
				linkElement.rel = 'noopener noreferrer';
				linkElement.innerHTML = currentHTML;
				removeTooltips( linkElement );
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
