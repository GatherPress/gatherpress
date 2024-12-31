/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state, actions } = store('gatherpress', {
	actions: {
		updateRsvp() {
			let status = 'not_attending';
			const element = getElement();
			const context = getContext();
			const postId = context?.postId || 0;

			if (
				['not_attending', 'no_status'].includes(
					state.posts[postId].userRsvpStatus ??
						getFromGlobal('eventDetails.currentUser.status')
				)
			) {
				status = 'attending';
			}

			const guests = 0;
			const anonymous = 0;

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getFromGlobal('misc.nonce'),
				},
				body: JSON.stringify({
					post_id: postId,
					status,
					guests,
					anonymous,
				}),
			})
				.then((response) => response.json()) // Parse the JSON response
				.then((res) => {
					if (res.success) {
						state.activePostId = postId;
						state.posts[postId] = {
							...state.posts[postId],
							eventResponses: {
								attending: res.responses.attending.count,
								waitingList: res.responses.waiting_list.count,
								notAttending: res.responses.not_attending.count,
							},
							userRsvpStatus: res.status,
							rsvpSelection: res.status,
						};
						actions.closeModal(null, element.ref);
					}
				})
				.catch(() => {});
		},
	},
	callbacks: {
		renderRsvpBlock() {
			const element = getElement();
			const context = getContext();
			const status =
				state.posts[context.postId]?.userRsvpStatus ??
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
