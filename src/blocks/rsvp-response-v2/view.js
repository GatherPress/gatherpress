/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext } from '../../helpers/interactivity';

const { state, actions } = store('gatherpress', {
	state: {
		posts: {},
	},
	actions: {
		processRsvpSelection(event) {
			// Call the linkHandler action to handle the default link behavior.
			actions.linkHandler(event);
			const element = getElement();

			if (element && element.ref) {
				const status = element.ref.getAttribute('data-status');

				if (status) {
					const context = getContext();
					const postId = context?.postId || 0;

					initPostContext(state, postId);

					if (postId) {
						state.posts[postId].rsvpSelection = status;
					}
				}
			}
		},
	},
	callbacks: {
		processRsvpDropdown() {
			const context = getContext();
			const postId = context?.postId || 0;

			initPostContext(state, postId);

			// Get the current element.
			const element = getElement();

			if (element && element.ref) {
				// Check if the `data-label` attribute is already set.
				if (!element.ref.hasAttribute('data-label')) {
					// Set `data-label` to the element's text content.
					const textContent = element.ref.textContent.trim();
					if (textContent) {
						element.ref.setAttribute('data-label', textContent);
					}
				}
			}

			// Fetch the current label and responses data.
			const parentElement = element.ref.parentElement;
			const classList = parentElement?.classList || [];
			const dataLabel = element.ref.getAttribute('data-label');
			const activeElement =
				element.ref.getAttribute('data-status') ===
					state.posts[postId].rsvpSelection ||
				('attending' === element.ref.getAttribute('data-status') &&
					'no_status' === state.posts[postId].rsvpSelection);

			const dropdownParent = element.ref.closest(
				'.wp-block-gatherpress-dropdown'
			);
			const triggerElement = dropdownParent.querySelector(
				'.wp-block-gatherpress-dropdown__trigger'
			);

			// Determine the count to replace %d with based on the class.
			let count = 0;
			if (classList.contains('gatherpress--rsvp-attending')) {
				count = state.posts[postId]?.eventResponses?.attending || 0;
			} else if (classList.contains('gatherpress--rsvp-waiting-list')) {
				count = state.posts[postId]?.eventResponses?.waitingList || 0;
			} else if (classList.contains('gatherpress--rsvp-not-attending')) {
				count = state.posts[postId]?.eventResponses?.notAttending || 0;
			}

			// Replace %d in the data-label with the count and update the text content.
			if (dataLabel) {
				const updatedText = dataLabel.replace('%d', count);
				element.ref.textContent = updatedText;
			}

			if (activeElement) {
				const activeText = element.ref.textContent.trim();
				const siblings =
					dropdownParent.querySelectorAll('[data-status]');
				siblings.forEach((sibling) => {
					sibling.classList.remove('gatherpress--is-disabled');
					sibling.removeAttribute('tabindex');
					sibling.removeAttribute('aria-disabled');
				});

				element.ref.classList.add('gatherpress--is-disabled');
				element.ref.setAttribute('tabindex', '-1');
				element.ref.setAttribute('aira-disabled', 'true');

				triggerElement.textContent = activeText;
			}

			if (
				0 === count &&
				!classList.contains('gatherpress--rsvp-attending')
			) {
				parentElement.classList.add('gatherpress--is-not-visible');
			} else {
				parentElement.classList.remove('gatherpress--is-not-visible');
			}

			const visibleItems = dropdownParent.querySelectorAll(
				'.wp-block-gatherpress-dropdown-item:not(.gatherpress--is-not-visible)'
			);

			// Check if "attending" is the only visible item.
			if (
				1 === visibleItems.length &&
				visibleItems[0].classList.contains(
					'gatherpress--rsvp-attending'
				)
			) {
				triggerElement.classList.add('gatherpress--is-disabled');
				triggerElement.setAttribute('tabindex', '-1');
			} else {
				triggerElement.classList.remove('gatherpress--is-disabled');
				triggerElement.setAttribute('tabindex', '0');
			}
		},
	},
});
