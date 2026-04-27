/**
 * WordPress dependencies.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { stripScriptsAndEventHandlers } from '../../helpers/globals';

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
					if ( res.success ) {
						const parent = element.ref.parentElement;

						Array.from( parent.children ).forEach( ( sibling ) => {
							if (
								sibling !== element.ref &&
								'id' in sibling.dataset
							) {
								sibling.remove();
							}
						} );

						const grandParent = parent.parentElement;
						const emptyRsvpMessageElement =
							grandParent.querySelector(
								'.gatherpress-rsvp-response--no-responses',
							);

						if ( emptyRsvpMessageElement ) {
							// Default to 'attending' to match the fetch status default above.
							const rsvpSelection =
								state.posts[ context.postId ]
									?.rsvpSelection || 'attending';

							const hasNoAttendees =
								[ 'attending', 'no_status' ].includes(
									rsvpSelection,
								) &&
								0 === res.responses.attending.count;

							if ( hasNoAttendees ) {
								emptyRsvpMessageElement.classList.add(
									'gatherpress--is-visible',
								);
								emptyRsvpMessageElement.classList.remove(
									'gatherpress--is-hidden',
								);
							} else {
								emptyRsvpMessageElement.classList.add(
									'gatherpress--is-hidden',
								);
								emptyRsvpMessageElement.classList.remove(
									'gatherpress--is-visible',
								);
							}

							// Toggle the inverse has-responses element.
							const hasResponsesElement =
								grandParent.querySelector(
									'.gatherpress-rsvp-response--has-responses',
								);

							if ( hasResponsesElement ) {
								if ( hasNoAttendees ) {
									hasResponsesElement.classList.add(
										'gatherpress--is-hidden',
									);
									hasResponsesElement.classList.remove(
										'gatherpress--is-visible',
									);
								} else {
									hasResponsesElement.classList.add(
										'gatherpress--is-visible',
									);
									hasResponsesElement.classList.remove(
										'gatherpress--is-hidden',
									);
								}
							}
						}

						// `res.content` is HTML rendered by GatherPress's own
						// `/rsvp-status-html` REST endpoint, which escapes
						// at template time. The strip pass here is
						// defense-in-depth — not a substitute for proper
						// server-side escaping.
						element.ref.insertAdjacentHTML(
							'beforebegin',
							stripScriptsAndEventHandlers( res.content ),
						);
					}
				} )
				.catch( () => {} );
		},
	},
} );
