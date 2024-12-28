/**
 * WordPress dependencies.
 */
import { store, getElement } from '@wordpress/interactivity';

store('gatherpress', {
	actions: {
		toggleDropdown(event) {
			event.preventDefault();
			const element = getElement();

			const menu = element.ref.parentElement.querySelector(
				'.wp-block-gatherpress-dropdown__menu'
			);
			const trigger = element.ref.parentElement.querySelector(
				'.wp-block-gatherpress-dropdown__trigger'
			);

			// Define focus trap logic
			const focusableSelectors = ['a[href]'];
			const focusableElements = [
				trigger, // Include the trigger for focus trapping
				...menu.querySelectorAll(focusableSelectors.join(',')),
			];
			const firstFocusableElement = focusableElements[0];
			const lastFocusableElement =
				focusableElements[focusableElements.length - 1];

			const handleFocusTrap = (e) => {
				if ('Tab' === e.key) {
					if (
						e.shiftKey &&
						document.activeElement === firstFocusableElement
					) {
						// Shift + Tab (backward navigation).
						e.preventDefault();
						lastFocusableElement.focus();
					} else if (
						!e.shiftKey &&
						document.activeElement === lastFocusableElement
					) {
						// Tab (forward navigation).
						e.preventDefault();
						firstFocusableElement.focus();
					}
				}
			};

			// Handle Escape key to close the dropdown.
			const handleEscapeKey = (e) => {
				if ('Escape' === e.key) {
					menu.classList.remove('gatherpress--is-visible');
					trigger.setAttribute('aria-expanded', 'false');
					trigger.focus();
				}

				if ('Escape' === e.key || 'Enter' === e.key) {
					cleanupEventListeners();
				}
			};

			// Cleanup event listeners.
			const cleanupEventListeners = () => {
				menu.removeEventListener('keydown', handleFocusTrap);
				trigger.removeEventListener('keydown', handleFocusTrap);
				global.document.removeEventListener('keydown', handleEscapeKey);
			};

			if (menu) {
				const isVisible = menu.classList.toggle(
					'gatherpress--is-visible'
				);

				// Update aria-expanded based on visibility.
				if (trigger) {
					trigger.setAttribute(
						'aria-expanded',
						isVisible ? 'true' : 'false'
					);
				}

				if (isVisible) {
					// Open dropdown: trap focus and add event listeners.
					firstFocusableElement.focus();

					menu.addEventListener('keydown', handleFocusTrap);
					trigger.addEventListener('keydown', handleFocusTrap);
					global.document.addEventListener(
						'keydown',
						handleEscapeKey
					);
				} else {
					// Close dropdown: remove event listeners.
					cleanupEventListeners();
				}
			}
		},
	},
});
