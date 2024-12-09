/**
 * WordPress dependencies.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state, actions } = store('gatherpress/rsvp', {
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

			const serializedInnerBlocks = JSON.parse(
				element.ref.getAttribute('data-serialized-inner-blocks')
			);
			const status =
				getFromGlobal('eventDetails.currentUser.status') ?? 'no_status';

			if (!serializedInnerBlocks || !serializedInnerBlocks[status]) {
				return;
			}

			const context = getContext();

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

						// Initialize interactivity for the new content.
						const interactiveElements =
							element.ref.querySelectorAll(
								'[data-wp-interactive]'
							);

						interactiveElements.forEach((el) => {
							// Extract the action string (e.g., "actions.rsvpOpenModal").
							const actionString =
								el.getAttribute('data-wp-on--click');

							if (
								actionString &&
								actionString.startsWith('actions.')
							) {
								// Dynamically resolve the action from the store.
								const actionName = actionString.replace(
									'actions.',
									''
								);
								const action = actions[actionName];

								// Validate and execute the resolved action.
								if (typeof action === 'function') {
									el.addEventListener('click', () =>
										action()
									);
								}
							}
						});
					}
				})
				.catch(() => {});
		},
	},
});
