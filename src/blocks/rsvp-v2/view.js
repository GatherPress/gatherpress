/**
 * WordPress dependencies.
 */
import {
	store,
	getContext,
	getElement,
	splitTask,
} from '@wordpress/interactivity';

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
	callbacks: {
		renderRsvpBlock() {
			const element = getElement();
			const context = getContext();

			const serializedInnerBlocks = JSON.parse(
				element.ref.getAttribute('data-serialized-inner-blocks')
			);
			const status =
				getFromGlobal('eventDetails.currentUser.status') ?? 'no_status';

			if (!serializedInnerBlocks || !serializedInnerBlocks[status]) {
				console.error('No inner blocks found for status:', status);
				return;
			}

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp-render', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getFromGlobal('misc.nonce'),
				},
				body: JSON.stringify({
					status: state.status,
					post_id: context.postId,
					block_data: serializedInnerBlocks[status],
				}),
			})
				.then((response) => response.json()) // Parse the JSON response
				.then((res) => {
					if (res.success) {
						element.ref.innerHTML = global.wp.dom.safeHTML(
							res.content
						);

						// Initialize interactivity for the new content
						const interactiveElements =
							element.ref.querySelectorAll(
								'[data-wp-interactive]'
							);

						interactiveElements.forEach(async (el) => {
							global.wp.htmlEntities(el);
							console.log(el);
							// console.log(await splitTask());
							// await splitTask();
						});
					}
				})
				.catch(() => {});
		},
	},
});
