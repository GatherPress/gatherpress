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

			if (menu) {
				const isVisible = menu.classList.toggle(
					'gatherpress--is-visible'
				);

				if (isVisible) {
					// Trap focus when the dropdown opens.
					const focusableSelectors = ['a[href]'];

					const focusableElements = menu.querySelectorAll(
						focusableSelectors.join(',')
					);

					const firstFocusableElement = focusableElements[0];
					const lastFocusableElement =
						focusableElements[focusableElements.length - 1];

					// Automatically focus the first focusable element.
					if (firstFocusableElement) {
						firstFocusableElement.focus();
					}

					// Trap focus within the dropdown menu.
					const handleFocusTrap = (e) => {
						if ('Tab' === e.key) {
							if (
								e.shiftKey &&
								global.document.activeElement ===
									firstFocusableElement
							) {
								// Shift + Tab (backward navigation).
								e.preventDefault();
								lastFocusableElement.focus();
							} else if (
								!e.shiftKey &&
								global.document.activeElement ===
									lastFocusableElement
							) {
								// Tab (forward navigation).
								e.preventDefault();
								firstFocusableElement.focus();
							}
						}
					};

					// Add keydown listener for trapping focus.
					menu.addEventListener('keydown', handleFocusTrap);
				}
			}
		},
	},
});
