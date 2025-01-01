/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';
import {
	initPostContext,
	sendRsvpApiRequest,
} from '../../helpers/interactivity';

const { state, actions } = store('gatherpress', {
	actions: {
		updateGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;
			const currentUser = state.posts[postId].currentUser;
			currentUser.guests = element.ref.value;

			initPostContext(state, postId);

			sendRsvpApiRequest(postId, currentUser, state);
		},
		updateRsvp(event = null) {
			if (event) {
				event.preventDefault();
			}

			const element = getElement();
			const context = getContext();
			const postId = context?.postId || 0;
			const setStatus = element.ref.getAttribute('data-set-status') ?? '';
			const currentUserStatus = state.posts[postId].currentUser.status;

			let status = 'not_attending';

			if (event) {
				if (
					['attending', 'waiting_list', 'not_attending'].includes(
						setStatus
					)
				) {
					status = setStatus;
				} else if (
					['not_attending', 'no_status'].includes(currentUserStatus)
				) {
					status = 'attending';
				}
			} else {
				status = currentUserStatus;
			}

			const guests = state.posts[postId].currentUser.guests;
			const anonymous = 0;
			sendRsvpApiRequest(
				postId,
				{
					status,
					guests,
					anonymous,
				},
				state,
				() => {
					actions.closeModal(null, element.ref);
				}
			);
		},
	},
	callbacks: {
		setGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext(state, postId);

			element.ref.value = state.posts[postId].currentUser.guests;
		},
		renderRsvpBlock() {
			const element = getElement();
			const context = getContext();
			const status =
				state.posts[context.postId]?.currentUser?.status ??
				getFromGlobal('eventDetails.currentUser.status');

			const innerBlocks =
				element.ref.querySelectorAll('[data-rsvp-status]');

			innerBlocks.forEach((innerBlock) => {
				const parent = innerBlock.parentNode;

				if (innerBlock.getAttribute('data-rsvp-status') === status) {
					innerBlock.style.display = '';

					// Move the visible block to the start of its parent.
					parent.insertBefore(innerBlock, parent.firstChild);
				} else {
					innerBlock.style.display = 'none';
				}
			});
		},
	},
});
