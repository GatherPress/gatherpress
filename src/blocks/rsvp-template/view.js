/**
 * WordPress dependencies.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state } = store('gatherpress/rsvp', {
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

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp-response-render', {
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

						element.ref.insertAdjacentHTML(
							'beforebegin',
							global.wp.dom.safeHTML(res.content)
						);
					}
				})
				.catch(() => {});
		},
	},
});
