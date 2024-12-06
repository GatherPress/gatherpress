/**
 * WordPress dependencies.
 */
import { store, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state } = store('gatherpress/rsvp-interactivity', {
	state: {
		attendingCount: getFromGlobal('eventDetails.responses.attending.count'),
	},
	actions: {
		rsvpOpenModal(e) {
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
						state.attendingCount = res.responses.attending.count;
						state.isOpen = true;
						console.log('SUCCESS');
					}
				})
				.catch((error) => {
					console.error('Error:', error);
				});
		},
	},
	callbacks: {
		logIsOpen() {
			if (state.isOpen) {
				alert('attending!');
			}
		}
	}
});
