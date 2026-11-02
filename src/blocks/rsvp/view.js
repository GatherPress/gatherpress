/**
 * WordPress dependencies
 */
import {
	store,
	getElement,
	getContext,
	withSyncEvent,
} from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import {
	initPostContext,
	sendRsvpApiRequest,
} from '../../helpers/interactivity';

/**
 * Runs a callback once an RSVP status container is shown.
 *
 * The `renderRsvpBlock` watch shows the container of the new status some
 * time after the RSVP request resolves, not at a fixed delay. Focus moved
 * before then lands on a hidden element, and the browser drops it to the
 * page body (#2428). Checks once per animation frame, and stops waiting
 * after 60 frames (about a second at 60 Hz), so the modal still closes when
 * the container never shows. Animation frames pause in a background tab,
 * so there the callback waits until the tab is visible again.
 *
 * @since 0.36.1
 *
 * @param {HTMLElement|null} container    The status container to wait for.
 * @param {Function}         callback     Receives true when the container is
 *                                        shown, false when waiting stopped.
 * @param {number}           [framesLeft] Animation frames left to wait.
 *
 * @return {void}
 */
function whenStatusShown( container, callback, framesLeft = 60 ) {
	if ( container && ! container.closest( '.gatherpress--is-hidden' ) ) {
		callback( true );
		return;
	}

	if ( ! container || 0 >= framesLeft ) {
		callback( false );
		return;
	}

	window.requestAnimationFrame( () =>
		whenStatusShown( container, callback, framesLeft - 1 ),
	);
}

const { state, actions } = store( 'gatherpress', {
	actions: {
		updateGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			const currentUser = state.posts[ postId ].currentUser;

			currentUser.guests = parseInt( element.ref.value, 10 );

			// Find the closest trigger element for loading state.
			const triggerElement = element.ref.closest( '.gatherpress-rsvp--trigger-update' );

			sendRsvpApiRequest( postId, currentUser, state, () => {
				// Use a short timeout to restore focus after data-wp-watch updates the DOM.
				setTimeout( () => {
					element.ref.focus();
				}, 10 );
			}, triggerElement );
		},
		updateAnonymous() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			const currentUser = state.posts[ postId ].currentUser;

			currentUser.anonymous = element.ref.checked ? 1 : 0;

			// Find the closest trigger element for loading state.
			const triggerElement = element.ref.closest( '.gatherpress-rsvp--trigger-update' );

			sendRsvpApiRequest( postId, currentUser, state, () => {
				// Use a short timeout to restore focus after data-wp-watch updates the DOM.
				setTimeout( () => {
					element.ref.focus();
				}, 10 );
			}, triggerElement );
		},
		// Synchronous, so preventDefault() stops the no-JavaScript form from submitting (#2039).
		updateRsvp: withSyncEvent( ( event = null ) => {
			if ( event ) {
				event.preventDefault();
			}

			const element = getElement();
			const context = getContext();
			const postId = context?.postId || 0;

			initPostContext( state, postId );

			const setStatus = element.ref.dataset.setStatus ?? '';
			const currentUserStatus = state.posts[ postId ].currentUser.status;

			let status = 'not_attending';

			if ( event ) {
				if (
					[ 'attending', 'waiting_list', 'not_attending' ].includes(
						setStatus,
					)
				) {
					status = setStatus;
				} else if (
					[ 'not_attending', 'no_status' ].includes( currentUserStatus )
				) {
					status = 'attending';
				}
			} else {
				status = currentUserStatus;
			}

			const guests = state.posts[ postId ].currentUser.guests;
			const anonymous = state.posts[ postId ].currentUser.anonymous;

			// Find the closest trigger element for loading state.
			const triggerElement = element.ref.closest( '.gatherpress-rsvp--trigger-update' );

			sendRsvpApiRequest(
				postId,
				{
					status,
					guests,
					anonymous,
				},
				state,
				( response ) => {
					const parentWithRsvpStatus =
						element.ref.closest( '[data-rsvp-status]' );
					const rsvpContainer = parentWithRsvpStatus?.closest(
						'.wp-block-gatherpress-rsvp',
					);

					// Bail if the expected wrappers aren't in the DOM
					// (malformed/partial block markup) so we don't throw on a
					// null reference and abort the post-RSVP UI update (#1719).
					if ( ! parentWithRsvpStatus || ! rsvpContainer ) {
						return;
					}

					const rsvpStatus =
						parentWithRsvpStatus.dataset.rsvpStatus;

					// Follow the status the server returned, not the one
					// requested: Attend on a full event returns
					// `waiting_list` (#2428).
					const newStatus =
						response?.status ??
						state.posts[ postId ].currentUser.status;
					const newStatusContainer = rsvpContainer.querySelector(
						`[data-rsvp-status="${ newStatus }"]`,
					);

					// Move focus only once the new state is on screen. Focus
					// aimed at a hidden state is dropped to the page body.
					// When the block has no container for the new status,
					// renderRsvpBlock shows the no_status one instead (#1719),
					// so wait for that.
					const shownContainer =
						newStatusContainer ??
						rsvpContainer.querySelector(
							'[data-rsvp-status="no_status"]',
						);

					whenStatusShown( shownContainer, ( shown ) => {
						// Only a real container of the new status has a modal
						// to switch to; the no_status fallback never does.
						const nextModalButton =
							shown &&
							newStatusContainer &&
							[ 'not_attending', 'no_status' ].includes( rsvpStatus ) &&
							[ 'attending', 'waiting_list' ].includes( newStatus )
								? newStatusContainer.querySelector(
									'.gatherpress-rsvp--trigger-update',
								)
								: null;

						if ( nextModalButton ) {
							actions.openModal( null, nextModalButton );
						}

						/**
						 * When switching to the new state's modal, close with
						 * findActiveSibling=false so focus stays on the newly
						 * opened modal manager. Otherwise fully close with
						 * findActiveSibling=true, so focus moves to the trigger
						 * of the visible state. That also covers a new state
						 * whose markup is missing (malformed or partial block
						 * content), where there is no modal to switch to (#1719).
						 */
						setTimeout( () => {
							actions.closeModal(
								null,
								element.ref,
								! nextModalButton,
							);
						}, 10 );
					} );
				},
				triggerElement,
			);
		} ),
	},
	callbacks: {
		monitorAnonymousStatus() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			element.ref.checked = state.posts[ postId ].currentUser.anonymous;
			// Saved as it changes, so Enter must not submit the no-JavaScript form (#2039).
			element.ref.removeAttribute( 'form' );
		},
		setGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			element.ref.value = state.posts[ postId ].currentUser.guests;
			// Saved as it changes, so Enter must not submit the no-JavaScript form (#2039).
			element.ref.removeAttribute( 'form' );
		},
		renderRsvpBlock() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			const userDetails = element.ref.dataset.userDetails
				? JSON.parse( element.ref.dataset.userDetails )
				: null;
			// Delete attribute after setting variable. This is just to kick things off...
			delete element.ref.dataset.userDetails;

			if ( userDetails ) {
				state.posts[ postId ] = {
					...state.posts[ postId ],
					currentUser: {
						status: userDetails?.status || 'no_status',
						guests: userDetails?.guests || 0,
						anonymous: userDetails?.anonymous || 0,
					},
				};
			}

			const innerBlocks =
				element.ref.querySelectorAll( '[data-rsvp-status]' );
			const currentStatus = state.posts[ postId ].currentUser.status;

			let matched = false;

			innerBlocks.forEach( ( innerBlock ) => {
				const parent = innerBlock.parentNode;
				if ( innerBlock.dataset.rsvpStatus === currentStatus ) {
					innerBlock.classList.remove( 'gatherpress--is-hidden' );
					// Guard against a spurious DOM remove+reinsert: when the block
					// is already firstChild, insertBefore(node, node) per the DOM
					// spec becomes insertBefore(node, node.nextSibling), which is
					// still a real mutation and restarts CSS animations on descendants.
					if ( parent.firstChild !== innerBlock ) {
						parent.insertBefore( innerBlock, parent.firstChild );
					}
					matched = true;
				} else {
					innerBlock.classList.add( 'gatherpress--is-hidden' );
				}
			} );

			// Fallback: if the saved block is missing the inner block for the
			// current status (malformed or partial markup), the loop above
			// hides every state and leaves nothing on screen. Re-show the
			// no_status block so the RSVP button never fully disappears (#1719).
			if ( ! matched ) {
				const fallback = element.ref.querySelector(
					'[data-rsvp-status="no_status"]',
				);

				if ( fallback ) {
					fallback.classList.remove( 'gatherpress--is-hidden' );
					// Same firstChild guard as above: re-inserting an already
					// first node is still a DOM mutation and restarts CSS
					// animations on descendants (#1741).
					if ( fallback.parentNode.firstChild !== fallback ) {
						fallback.parentNode.insertBefore(
							fallback,
							fallback.parentNode.firstChild,
						);
					}
				}
			}
		},
		updateGuestCountDisplay() {
			const context = getContext();
			const postId = context?.postId || 0;

			// Ensure the state is initialized.
			initPostContext( state, context );

			// Retrieve the current guest count from the state.
			const guestCount = parseInt(
				state.posts[ postId ]?.currentUser?.guests || 0,
				10,
			);

			// Get the current element.
			const element = getElement();

			// Get the singular and plural labels from the data attributes.
			const singularLabel = element.ref.dataset.guestSingular;
			const pluralLabel = element.ref.dataset.guestPlural;

			// Determine the text to display based on the guest count.
			let text = '';

			if ( 0 < guestCount ) {
				text =
					1 === guestCount
						? singularLabel.replace( '%d', guestCount )
						: pluralLabel.replace( '%d', guestCount );
			}

			// Update the element's text content.
			element.ref.textContent = text;

			if ( 0 < guestCount ) {
				element.ref.classList.remove( 'gatherpress--is-hidden' );
			} else {
				element.ref.classList.add( 'gatherpress--is-hidden' );
			}
		},
	},
} );
