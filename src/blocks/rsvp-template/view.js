/**
 * WordPress dependencies.
 */
import {
	store,
	getContext,
	getElement,
	useState,
} from '@wordpress/interactivity';
import { sanitizeHtml } from '../../helpers/globals';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const { state } = store('gatherpress/rsvp', {
	callbacks: {
		renderBlocks() {
			const element = getElement();
			const context = getContext();

			fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp-render', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getFromGlobal('misc.nonce'),
				},
				body: JSON.stringify({
					// comment_id: 109,
					status: state.status,
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
							sanitizeHtml(res.content)
						);
						console.log('SUCCESS');
					}
				})
				.catch((error) => {
					console.error('Error:', error);
				});
		},
	},
});
