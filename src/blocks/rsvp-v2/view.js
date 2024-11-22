import { store } from '@wordpress/interactivity';
// import apiFetch from '@wordpress/api-fetch';
// import {getFromGlobal} from '../../helpers/globals';

store("gatherpress/rsvp-interactivity", {
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

			fetch('http://gatherpress-org.test/wp-json/gatherpress/v1/event/rsvp?_locale=user', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': global.GatherPress.misc.nonce, // Include nonce in headers for authentication
				},
				body: JSON.stringify({
					post_id: global.GatherPress.eventDetails.postId,
					status,
					guests,
					anonymous,
				}),
			})
			.then((response) => response.json()) // Parse the JSON response
			.then((res) => {
				if (res.success) {
					console.log('SUCCESS');
				}
			})
			.catch((error) => {
				console.error('Error:', error);
			});
		}
	},
});
