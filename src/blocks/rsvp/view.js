/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import {
	initPostContext,
	sendRsvpApiRequest,
} from '../../helpers/interactivity';
import { getUrlParam } from '../../helpers/globals';

const { state, actions } = store( 'gatherpress', {
	actions: {
		updateGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			const currentUser = state.posts[ postId ].currentUser;

			currentUser.guests = parseInt( element.ref.value, 10 );
			currentUser.rsvpToken = getUrlParam( 'gatherpress_rsvp_token' );

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
			currentUser.rsvpToken = getUrlParam( 'gatherpress_rsvp_token' );

			// Find the closest trigger element for loading state.
			const triggerElement = element.ref.closest( '.gatherpress-rsvp--trigger-update' );

			sendRsvpApiRequest( postId, currentUser, state, () => {
				// Use a short timeout to restore focus after data-wp-watch updates the DOM.
				setTimeout( () => {
					element.ref.focus();
				}, 10 );
			}, triggerElement );
		},
		updateRsvp( event = null ) {
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
			const rsvpToken = getUrlParam( 'gatherpress_rsvp_token' );

			// Find the closest trigger element for loading state.
			const triggerElement = element.ref.closest( '.gatherpress-rsvp--trigger-update' );

			sendRsvpApiRequest(
				postId,
				{
					status,
					guests,
					anonymous,
					rsvpToken,
				},
				state,
				() => {
					const parentWithRsvpStatus =
						element.ref.closest( '[data-rsvp-status]' );
					const rsvpStatus =
						parentWithRsvpStatus.dataset.rsvpStatus;
					const rsvpContainer = parentWithRsvpStatus.closest(
						'.wp-block-gatherpress-rsvp',
					);

					if ( [ 'not_attending', 'no_status' ].includes( rsvpStatus ) ) {
						const attendingStatusButton =
							rsvpContainer.querySelector(
								'[data-rsvp-status="attending"] .gatherpress-rsvp--trigger-update',
							);

						actions.openModal( null, attendingStatusButton );

						/**
						 * When keeping a modal open after an action, use findActiveSibling=false
						 * to prevent focus from moving to a different modal manager.
						 */
						setTimeout( () => {
							actions.closeModal( null, element.ref, false );
						}, 10 );
					} else {
						/**
						 * When fully closing a modal, use findActiveSibling=true
						 * to allow focus to move to any visible modal manager in sibling containers.
						 */
						setTimeout( () => {
							actions.closeModal( null, element.ref, true );
						}, 10 );
					}
				},
				triggerElement,
			);
		},
	},
	callbacks: {
		monitorAnonymousStatus() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			element.ref.checked = state.posts[ postId ].currentUser.anonymous;
		},
		setGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext( state, postId );

			element.ref.value = state.posts[ postId ].currentUser.guests;
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

			innerBlocks.forEach( ( innerBlock ) => {
				const parent = innerBlock.parentNode;
				if (
					innerBlock.dataset.rsvpStatus ===
					state.posts[ postId ].currentUser.status
				) {
					innerBlock.classList.remove( 'gatherpress--is-hidden' );
					// Move the visible block to the start of its parent.
					parent.insertBefore( innerBlock, parent.firstChild );
				} else {
					innerBlock.classList.add( 'gatherpress--is-hidden' );
				}
			} );
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
