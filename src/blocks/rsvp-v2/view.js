import { store } from '@wordpress/interactivity';

store("gatherpress/rsvp-interactivity", {
	actions: {
		handleRSVPClick() {
			const modal = document.querySelector('.gatherpress-rsvp-modal');

			if (modal) {
				modal.classList.toggle('gatherpress-is-visible');
			}
		}
	},
});
