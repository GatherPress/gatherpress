/**
 * WordPress dependencies.
 */
import { store, getElement } from '@wordpress/interactivity';

const { actions } = store('gatherpress', {
	actions: {
		preventDefault(event) {
			event.preventDefault();
		},
		linkHandler(event) {
			// Prevent the default link behavior
			actions.preventDefault(event);

			// Get the clicked element
			const element = getElement();

			// Find the parent `.wp-block-gatherpress-dropdown`
			const dropdownParent = element.ref.closest(
				'.wp-block-gatherpress-dropdown'
			);

			// If the dropdown is in select mode
			if (
				dropdownParent &&
				dropdownParent.dataset.dropdownMode === 'select'
			) {
				// Get the dropdown menu and trigger
				const dropdownMenu = dropdownParent.querySelector(
					'.wp-block-gatherpress-dropdown__menu'
				);
				const dropdownTrigger = dropdownParent.querySelector(
					'.wp-block-gatherpress-dropdown__trigger'
				);

				// If the clicked anchor is already disabled, prevent further action
				const clickedItem = element.ref.closest(
					'.wp-block-gatherpress-dropdown-item'
				);
				if (clickedItem) {
					const clickedAnchor = clickedItem.querySelector('a');
					if (
						clickedAnchor &&
						clickedAnchor.classList.contains(
							'gatherpress--is-disabled'
						)
					) {
						return;
					}

					// Disable the clicked item
					if (clickedAnchor) {
						clickedAnchor.classList.add('gatherpress--is-disabled');
						clickedAnchor.setAttribute('tabindex', '-1');
						clickedAnchor.setAttribute('aira-disabled', 'true');
					}

					// Enable siblings
					const siblingItems = dropdownMenu.querySelectorAll(
						'.wp-block-gatherpress-dropdown-item'
					);
					siblingItems.forEach((sibling) => {
						const siblingAnchor = sibling.querySelector('a');

						if (siblingAnchor && sibling !== clickedItem) {
							siblingAnchor.classList.remove(
								'gatherpress--is-disabled'
							);
							siblingAnchor.removeAttribute('tabindex');
							siblingAnchor.removeAttribute('aria-disabled');
						}
					});

					// Update the dropdown trigger text
					if (dropdownTrigger && clickedAnchor) {
						dropdownTrigger.textContent =
							clickedAnchor.textContent.trim();
					}

					// Close the dropdown menu
					if (dropdownMenu) {
						dropdownMenu.classList.remove(
							'gatherpress--is-visible'
						);
						dropdownTrigger.setAttribute('aria-expanded', 'false');
					}
				}
			}
		},
		toggleDropdown(event) {
			actions.preventDefault(event);
			const element = getElement();

			const menu = element.ref.parentElement.querySelector(
				'.wp-block-gatherpress-dropdown__menu'
			);
			const trigger = element.ref.parentElement.querySelector(
				'.wp-block-gatherpress-dropdown__trigger'
			);

			// Define focus trap logic.
			const focusableSelectors = [
				'a[href]:not(.gatherpress--is-disabled)',
			];
			const focusableElements = [
				trigger, // Include the trigger for focus trapping.
				...menu.querySelectorAll(focusableSelectors.join(',')),
			];
			const firstFocusableElement = focusableElements[0];
			const lastFocusableElement =
				focusableElements[focusableElements.length - 1];

			const handleFocusTrap = (e) => {
				if ('Tab' === e.key) {
					if (
						e.shiftKey &&
						global.document.activeElement === firstFocusableElement
					) {
						// Shift + Tab (backward navigation).
						e.preventDefault();
						lastFocusableElement.focus();
					} else if (
						!e.shiftKey &&
						global.document.activeElement === lastFocusableElement
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
