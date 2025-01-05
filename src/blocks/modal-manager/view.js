/**
 * WordPress dependencies.
 */
import { store } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { manageFocusTrap } from '../../helpers/interactivity';

const { actions } = store('gatherpress', {
	actions: {
		openModal(event = null, element = null) {
			if (event) {
				event.preventDefault();
			}

			element = element ?? event.target;

			const modalManager = element.closest(
				'.wp-block-gatherpress-modal-manager'
			);

			if (modalManager) {
				const modal = modalManager.querySelector(
					'.wp-block-gatherpress-modal'
				);

				if (modal) {
					modal.classList.add('gatherpress--is-visible');

					const modalContent = modal.querySelector(
						'.wp-block-gatherpress-modal-content'
					);

					if (modalContent) {
						// Define focusable elements inside the modal.
						const focusableSelectors = [
							'a[href]',
							'button:not([disabled])',
							'textarea:not([disabled])',
							'input[type="text"]:not([disabled])',
							'input[type="number"]:not([disabled])',
							'input[type="radio"]:not([disabled])',
							'input[type="checkbox"]:not([disabled])',
							'select:not([disabled])',
							'[tabindex]:not([tabindex="-1"])',
						];
						const focusableElements = Array.from(
							modalContent.querySelectorAll(
								focusableSelectors.join(',')
							)
						);

						// Focus the first focusable element, if available.
						if (focusableElements[0]) {
							setTimeout(() => {
								modal.setAttribute('aria-hidden', 'false');
								focusableElements[0].focus();
							}, 1);
						}

						// Set up focus trap using the helper function and store cleanup.
						modalContent.cleanupFocusTrap =
							manageFocusTrap(focusableElements);
					}

					// Handle modal close logic.
					const closeButton = modal.querySelector(
						'.gatherpress--close-modal'
					);

					if (closeButton) {
						closeButton.addEventListener('click', () => {
							modal.classList.remove('gatherpress--is-visible');
							modal.setAttribute('aria-hidden', 'true');

							// Clean up focus trap if applicable.
							if (
								modalContent &&
								'function' ===
									typeof modalContent.cleanupFocusTrap
							) {
								modalContent.cleanupFocusTrap();
							}
						});
					}
				}
			}
		},
		openModalOnEnter(event) {
			if ('Enter' === event.key) {
				actions.openModal(event);
			}
		},
		closeModal(event = null, element = null) {
			if (event) {
				event.preventDefault();
			}

			element = element ?? event.target;

			const modalManager = element.closest(
				'.wp-block-gatherpress-modal-manager'
			);

			if (modalManager) {
				const modal = modalManager.querySelector(
					'.wp-block-gatherpress-modal'
				);

				if (modal) {
					modal.classList.remove('gatherpress--is-visible');
					modal.setAttribute('aria-hidden', 'true');
				}
			}
		},
		closeModalOnEnter(event) {
			if ('Enter' === event.key) {
				actions.closeModal(event);
			}
		},
	},
});
