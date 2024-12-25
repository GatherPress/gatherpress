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

			if (
				!state.rsvpResponseStatus ||
				context.postId !== state.activePostId
			) {
				return;
			}

			const element = getElement();

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp-status-html', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getFromGlobal('misc.nonce'),
				},
				body: JSON.stringify({
					status: state.rsvpResponseStatus,
					post_id: context.postId,
					block_data: element.attributes['data-blocks'],
				}),
			})
				.then((response) => response.json()) // Parse the JSON response
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
							if (0 === res.responses.attending.count) {
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
