/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext } from '../../helpers/interactivity';

// eslint-disable-next-line no-console
console.log( '[online-event-v2] VIEW.JS LOADED' );

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
			const element = getElement();
			const context = getContext();
			const postId = context?.postId || 0;
			const linkText = context?.linkText || '';

			// eslint-disable-next-line no-console
			console.log( '[online-event-v2] Callback fired', { postId, linkText } );

			if ( ! postId ) {
				// eslint-disable-next-line no-console
				console.log( '[online-event-v2] No postId, skipping' );
				return;
			}

			initPostContext( state, postId );

			const currentElement = element.ref.querySelector( '.gatherpress-online-event__text' );

			if ( ! currentElement ) {
				// eslint-disable-next-line no-console
				console.log( '[online-event-v2] No element found' );
				return;
			}

			const isLink = 'A' === currentElement.tagName;

			// Initialize state from DOM on first run.
			if ( undefined === state.posts[ postId ].onlineEventLink ) {
				// eslint-disable-next-line no-console
				console.log( '[online-event-v2] First run, initializing from DOM' );
				state.posts[ postId ].onlineEventLink = isLink ? currentElement.href : '';
				// Don't manipulate DOM on first run - PHP already rendered it correctly.
				return;
			}

			// Access state.posts[postId].onlineEventLink for reactivity.
			const onlineEventLink = state.posts[ postId ]?.onlineEventLink || '';
			const hasLink = '' !== onlineEventLink;

			// eslint-disable-next-line no-console
			console.log( '[online-event-v2] State:', {
				onlineEventLink,
				hasLink,
				fullState: state.posts[ postId ],
			} );

			// eslint-disable-next-line no-console
			console.log( '[online-event-v2] Element:', {
				tagName: currentElement.tagName,
				isLink,
			} );

			if ( hasLink && ! isLink ) {
				// eslint-disable-next-line no-console
				console.log( '[online-event-v2] Converting span to link' );
				const linkElement = document.createElement( 'a' );
				linkElement.className = 'gatherpress-online-event__text';
				linkElement.href = onlineEventLink;
				linkElement.target = '_blank';
				linkElement.rel = 'noopener noreferrer';
				linkElement.textContent = linkText;
				currentElement.replaceWith( linkElement );
			} else if ( ! hasLink && isLink ) {
				// eslint-disable-next-line no-console
				console.log( '[online-event-v2] Converting link to span' );
				const spanElement = document.createElement( 'span' );
				spanElement.className = 'gatherpress-online-event__text';
				spanElement.textContent = linkText;
				currentElement.replaceWith( spanElement );
			} else if ( hasLink && isLink && currentElement.href !== onlineEventLink ) {
				// eslint-disable-next-line no-console
				console.log( '[online-event-v2] Updating href' );
				currentElement.href = onlineEventLink;
			}
		},
	},
} );
