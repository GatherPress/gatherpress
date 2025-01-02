/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext } from '../../helpers/interactivity';

const { state } = store('gatherpress', {
	callbacks: {
		updateGuestCountDisplay() {
			const context = getContext();
			const postId = context?.postId || 0;

			// Ensure the state is initialized.
			initPostContext(state, context);

			// Retrieve the current guest count from the state.
			const guestCount = parseInt(
				state.posts[postId]?.currentUser?.guests || 0,
				10
			);

			// Get the current element.
			const element = getElement();

			// Get the singular and plural labels from the data attributes.
			const singularLabel = element.ref.getAttribute(
				'data-guest-singular'
			);
			const pluralLabel = element.ref.getAttribute('data-guest-plural');

			// Determine the text to display based on the guest count.
			let text = '';

			if (0 < guestCount) {
				text =
					1 === guestCount
						? singularLabel.replace('%d', guestCount)
						: pluralLabel.replace('%d', guestCount);
			}

			// Update the element's text content.
			element.ref.textContent = text;
		},
	},
});
