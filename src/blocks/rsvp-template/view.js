/**
 * WordPress dependencies.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal, safeHTML } from '../../helpers/globals';

const { state } = store( 'gatherpress', {
	callbacks: {
		renderBlocks() {
			const context = getContext();
			const element = getElement();
			const rsvpResponseElement = element.ref.closest(
				'.wp-block-gatherpress-rsvp-response',
			);
			fetch( getFromGlobal( 'urls.eventApiUrl' ) + '/rsvp-status-html', {
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
							if (
								[ 'attending', 'no_status' ].includes(
									state.posts[ context.postId ]?.rsvpSelection,
								) &&
								0 === res.responses.attending.count
							) {
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
						}

						element.ref.insertAdjacentHTML(
							'beforebegin',
							safeHTML( res.content ),
						);
					}
				} )
				.catch( () => {} );
		},
	},
} );
