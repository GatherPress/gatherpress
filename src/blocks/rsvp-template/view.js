/**
 * WordPress dependencies.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal, safeHTML } from '../../helpers/globals';

const { state } = store('gatherpress', {
	callbacks: {
		renderBlocks() {
			const context = getContext();
			const element = getElement();

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp-status-html', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getFromGlobal('misc.nonce'),
				},
				body: JSON.stringify({
					status:
						state.posts[context.postId]?.rsvpSelection ||
						'attending',
					post_id: context.postId,
					block_data: element.ref.textContent,
				}),
			})
				.then((response) => response.json()) // Parse the JSON response.
				.then((res) => {
					if (res.success) {
						const parent = element.ref.parentElement;

						Array.from(parent.children).forEach((sibling) => {
							if (
								sibling !== element.ref &&
								sibling.hasAttribute('data-id')
							) {
								sibling.remove();
							}
						});

						const grandParent = parent.parentElement;
						const emptyRsvpMessageElement =
							grandParent.querySelector(
								'.gatherpress--empty-rsvp'
							);

						if (emptyRsvpMessageElement) {
							if (
								['attending', 'no_status'].includes(
									state.posts[context.postId]?.rsvpSelection
								) &&
								0 === res.responses.attending.count
							) {
								emptyRsvpMessageElement.classList.add(
									'gatherpress--is-visible'
								);
								emptyRsvpMessageElement.classList.remove(
									'gatherpress--is-not-visible'
								);
							} else {
								emptyRsvpMessageElement.classList.add(
									'gatherpress--is-not-visible'
								);
								emptyRsvpMessageElement.classList.remove(
									'gatherpress--is-visible'
								);
							}
						}

						element.ref.insertAdjacentHTML(
							'beforebegin',
							safeHTML(res.content)
						);
					}
				})
				.catch(() => {});
		},
	},
});
