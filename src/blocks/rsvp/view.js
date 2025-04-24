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

const { state, actions } = store('gatherpress', {
	actions: {
		updateGuestCount() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;
			const currentUser = state.posts[postId].currentUser;

			currentUser.guests = parseInt(element.ref.value, 10);

			initPostContext(state, postId);

			sendRsvpApiRequest(postId, currentUser, state, () => {
				// Use a short timeout to restore focus after data-wp-watch updates the DOM.
				setTimeout(() => {
					element.ref.focus();
				}, 1);
			});
		},
		updateAnonymous() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;
			const currentUser = state.posts[postId].currentUser;

			currentUser.anonymous = element.ref.checked ? 1 : 0;

			initPostContext(state, postId);

			sendRsvpApiRequest(postId, currentUser, state, () => {
				// Use a short timeout to restore focus after data-wp-watch updates the DOM.
				setTimeout(() => {
					element.ref.focus();
				}, 1);
			});
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
			const anonymous = state.posts[postId].currentUser.anonymous;

			sendRsvpApiRequest(
				postId,
				{
					status,
					guests,
					anonymous,
				},
				state,
				() => {
					const parentWithRsvpStatus =
						element.ref.closest('[data-rsvp-status]');
					const rsvpStatus =
						parentWithRsvpStatus.getAttribute('data-rsvp-status');
					const rsvpContainer = parentWithRsvpStatus.closest(
						'.wp-block-gatherpress-rsvp'
					);

					if (['not_attending', 'no_status'].includes(rsvpStatus)) {
						const attendingStatusButton =
							rsvpContainer.querySelector(
								'[data-rsvp-status="attending"] .gatherpress--update-rsvp'
							);

						actions.openModal(null, attendingStatusButton);
					}

					// Close the current modal after a short delay to prevent flicker.
					setTimeout(() => {
						actions.closeModal(null, element.ref);
					}, 1);
				}
			);
		},
	},
	callbacks: {
		monitorAnonymousStatus() {
			const element = getElement();
			const context = getContext();
			const postId = context.postId || 0;

			initPostContext(state, postId);

			element.ref.checked = state.posts[postId].currentUser.anonymous;
		},
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
			const postId = context.postId || 0;

			initPostContext(state, postId);

			const userDetails = JSON.parse(
				element.ref.getAttribute('data-user-details')
			);
			// Delete attribute after setting variable. This is just to kick things off...
			element.ref.removeAttribute('data-user-details');

			if (userDetails) {
				state.posts[postId] = {
					...state.posts[postId],
					currentUser: {
						status: userDetails?.status || 'no_status',
						guests: userDetails?.guests || 0,
						anonymous: userDetails?.anonymous || 0,
					},
				};
			}

			const innerBlocks =
				element.ref.querySelectorAll('[data-rsvp-status]');

			innerBlocks.forEach((innerBlock) => {
				const parent = innerBlock.parentNode;
				if (
					innerBlock.getAttribute('data-rsvp-status') ===
					state.posts[postId].currentUser.status
				) {
					innerBlock.classList.remove('gatherpress--is-not-visible');
					// Move the visible block to the start of its parent.
					parent.insertBefore(innerBlock, parent.firstChild);
				} else {
					innerBlock.classList.add('gatherpress--is-not-visible');
				}
			});
		},
		updateGuestCountDisplay() {
			const context = getContext();
			const postId = context?.postId || 0;

			// Ensure the state is initialized.
			initPostContext(state, context);

			// Retrieve the current guest count from the state.
			const guestCount = parseInt(
				state.posts[postId]?.currentUser?.guests || 0,
				10
			);

			// Get the current element.
			const element = getElement();

			// Get the singular and plural labels from the data attributes.
			const singularLabel = element.ref.getAttribute(
				'data-guest-singular'
			);
			const pluralLabel = element.ref.getAttribute('data-guest-plural');

			// Determine the text to display based on the guest count.
			let text = '';

			if (0 < guestCount) {
				text =
					1 === guestCount
						? singularLabel.replace('%d', guestCount)
						: pluralLabel.replace('%d', guestCount);
			}

			// Update the element's text content.
			element.ref.textContent = text;

			if (0 < guestCount) {
				element.ref.classList.remove('gatherpress--is-not-visible');
			} else {
				element.ref.classList.add('gatherpress--is-not-visible');
			}
		},
	},
});
