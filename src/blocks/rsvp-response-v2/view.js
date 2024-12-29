/**
 * WordPress dependencies.
 */
import { store, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

store('gatherpress', {
	callbacks: {
		processRsvpDropdown() {
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
			const responses = getFromGlobal('eventDetails.responses');
			const activeElement = element.ref.classList.contains(
				'gatherpress--is-disabled'
			);
			const dropdownParent = element.ref.closest(
				'.wp-block-gatherpress-dropdown'
			);

			// Determine the count to replace %d with based on the class.
			let count = 0;

			if (classList.contains('gatherpress--rsvp-attending')) {
				count = responses?.attending?.count || 0;
			} else if (classList.contains('gatherpress--rsvp-waiting-list')) {
				count = responses?.waiting_list?.count || 0;
			} else if (classList.contains('gatherpress--rsvp-not-attending')) {
				count = responses?.not_attending?.count || 0;
			}

			// Replace %d in the data-label with the count and update the text content.
			if (dataLabel) {
				const updatedText = dataLabel.replace('%d', count);
				element.ref.textContent = updatedText;
			}

			if (activeElement) {
				const activeText = element.ref.textContent.trim();
				const triggerElement = dropdownParent.querySelector(
					'.wp-block-gatherpress-dropdown__trigger'
				);

				if (triggerElement) {
					triggerElement.textContent = activeText;
				}
			}
		},
	},
});
