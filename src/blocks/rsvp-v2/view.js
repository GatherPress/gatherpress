/**
 * WordPress dependencies.
 */
import { store, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state } = store('gatherpress', {
	state: {
		rsvpStatus:
			getFromGlobal('eventDetails.currentUser.status') ?? 'no_status',
	},
	actions: {
		updateRsvp() {
			let status = 'not_attending';

			if (['not_attending', 'no_status'].includes(state.rsvpStatus)) {
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
					post_id: getFromGlobal('eventDetails.postId'),
					status,
					guests,
					anonymous,
				}),
			})
				.then((response) => response.json()) // Parse the JSON response
				.then((res) => {
					if (res.success) {
						state.activePostId = res.event_id;
						state.rsvpResponseStatus = res.status;
						state.rsvpStatus = res.status;
					}
				})
				.catch(() => {});
		},
	},
	callbacks: {
		renderRsvpBlock() {
			const element = getElement();
			const status =
				state.rsvpStatus ??
				getFromGlobal('eventDetails.currentUser.status');
			const innerBlocks =
				element.ref.querySelectorAll('[data-rsvp-status]');

			innerBlocks.forEach((innerBlock) => {
				if (innerBlock.getAttribute('data-rsvp-status') === status) {
					innerBlock.style.display = '';
				} else {
					innerBlock.style.display = 'none';
				}
			});
		},
	},
});
