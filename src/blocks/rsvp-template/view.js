/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { stripScriptsAndEventHandlers } from '../../helpers/globals';

/**
 * Toggle a `.gatherpress--is-visible` / `.gatherpress--is-hidden` class
 * pair on an element. Centralized so the empty-/has-responses pair share
 * one implementation and the caller stays a one-liner.
 *
 * @param {Element} element The element to toggle.
 * @param {boolean} visible Whether the element should be visible.
 */
const setResponseVisibility = ( element, visible ) => {
	element.classList.add(
		visible ? 'gatherpress--is-visible' : 'gatherpress--is-hidden',
	);
	element.classList.remove(
		visible ? 'gatherpress--is-hidden' : 'gatherpress--is-visible',
	);
};

/**
 * Update the empty-/has-responses message pair next to the rendered RSVP
 * template. Extracted from the renderBlocks `.then` callback so that
 * callback stays under SonarCloud's cognitive-complexity threshold.
 *
 * @param {Element} grandParent    Container holding the message elements.
 * @param {string}  rsvpSelection  Currently selected RSVP filter.
 * @param {number}  attendingCount Number of attendees in the response.
 */
const updateEmptyResponseMessages = (
	grandParent,
	rsvpSelection,
	attendingCount,
) => {
	const emptyEl = grandParent.querySelector(
		'.gatherpress-rsvp-response--no-responses',
	);

	if ( ! emptyEl ) {
		return;
	}

	const hasNoAttendees =
		[ 'attending', 'no_status' ].includes( rsvpSelection ) &&
		0 === attendingCount;

	setResponseVisibility( emptyEl, hasNoAttendees );

	const hasResponsesEl = grandParent.querySelector(
		'.gatherpress-rsvp-response--has-responses',
	);

	if ( hasResponsesEl ) {
		setResponseVisibility( hasResponsesEl, ! hasNoAttendees );
	}
};

const { state } = store( 'gatherpress', {
	callbacks: {
		renderBlocks() {
			const context = getContext();
			const element = getElement();
			const rsvpResponseElement = element.ref.closest(
				'.wp-block-gatherpress-rsvp-response',
			);
			fetch( state.eventApiUrl + '/rsvp-status-html', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					status:
						state.posts[ context.postId ]?.rsvpSelection ||
						'attending',
					post_id: context.postId,
					block_data: element.ref.dataset.blockTemplate,
					limit_enabled:
						'1' === rsvpResponseElement.dataset.limitEnabled,
					limit: parseInt( rsvpResponseElement.dataset.limit, 10 ),
				} ),
			} )
				.then( ( response ) => response.json() ) // Parse the JSON response.
				.then( ( res ) => {
					if ( ! res.success ) {
						return;
					}

					const parent = element.ref.parentElement;

					Array.from( parent.children ).forEach( ( sibling ) => {
						if (
							sibling !== element.ref &&
							'id' in sibling.dataset
						) {
							sibling.remove();
						}
					} );

					updateEmptyResponseMessages(
						parent.parentElement,
						state.posts[ context.postId ]?.rsvpSelection ||
							'attending',
						res.responses.attending.count,
					);

					// `res.content` is HTML rendered by GatherPress's own
					// `/rsvp-status-html` REST endpoint, which escapes
					// at template time. The strip pass here is
					// defense-in-depth — not a substitute for proper
					// server-side escaping.
					element.ref.insertAdjacentHTML(
						'beforebegin',
						stripScriptsAndEventHandlers( res.content ),
					);
				} )
				.catch( () => {} );
		},
	},
} );
