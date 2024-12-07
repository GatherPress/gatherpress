/**
 * WordPress dependencies.
 */
import { store } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state } = store('gatherpress/rsvp', {
	actions: {
		rsvpOpenModal() {
			const modal = document.querySelector('.gatherpress-rsvp-modal');

			if (modal) {
				modal.classList.add('gatherpress--is-visible');
			}
		},
		rsvpCloseModal() {
			const modal = document.querySelector('.gatherpress-rsvp-modal');

			if (modal) {
				modal.classList.remove('gatherpress--is-visible');
			}
		},
		rsvpStatusAttending() {
			const status = 'attending';
			const guests = 0;
			const anonymous = 0;

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getFromGlobal('misc.nonce'),
				},
				body: JSON.stringify({
					// post_id: global.GatherPress.eventDetails.postId,
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
						state.status = res.status;
					}
				})
				.catch(() => {});
		},
	},
});
