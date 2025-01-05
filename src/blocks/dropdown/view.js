/**
 * WordPress dependencies.
 */
import { store, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import {
	manageFocusTrap,
	setupCloseHandlers,
} from '../../helpers/interactivity';

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

			if (!menu || !trigger) {
				return;
			}

			const isVisible = menu.classList.toggle('gatherpress--is-visible');
			trigger.setAttribute('aria-expanded', isVisible ? 'true' : 'false');

			// Create focusable elements array
			const focusableSelectors = [
				'a[href]:not(.gatherpress--is-disabled)',
			];
			const focusableElements = [
				trigger,
				...menu.querySelectorAll(focusableSelectors.join(',')),
			];

			if (isVisible) {
				// Open dropdown: set focus trap and close handlers
				trigger.focus();

				// Set up focus trap
				element.ref.cleanupFocusTrap =
					manageFocusTrap(focusableElements);

				// Set up close handlers
				element.ref.cleanupCloseHandlers = setupCloseHandlers(
					'.wp-block-gatherpress-dropdown__menu',
					'.wp-block-gatherpress-dropdown__menu',
					(dropdown) => {
						// Close the dropdown and clean up
						dropdown.classList.remove('gatherpress--is-visible');
						trigger.setAttribute('aria-expanded', 'false');

						// Cleanup focus trap
						if (
							'function' === typeof element.ref.cleanupFocusTrap
						) {
							element.ref.cleanupFocusTrap();
						}
					}
				);
			} else {
				// Close dropdown: clean up focus trap and close handlers
				if ('function' === typeof element.ref.cleanupFocusTrap) {
					element.ref.cleanupFocusTrap();
				}
				if ('function' === typeof element.ref.cleanupCloseHandlers) {
					element.ref.cleanupCloseHandlers();
				}
			}
		},
	},
});
