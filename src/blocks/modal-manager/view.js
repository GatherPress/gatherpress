/**
 * WordPress dependencies.
 */
import { store } from '@wordpress/interactivity';

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
					modal.setAttribute('aria-hidden', 'false');

					const modalContent = modal.querySelector(
						'.wp-block-gatherpress-modal-content'
					);

					// Trap focus when the modal opens.
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

					const focusableElements = modalContent.querySelectorAll(
						focusableSelectors.join(',')
					);

					const firstFocusableElement = focusableElements[0];
					const lastFocusableElement =
						focusableElements[focusableElements.length - 1];

					// Automatically focus the first focusable element.
					if (firstFocusableElement) {
						firstFocusableElement.focus();
					}

					// Trap focus within the modal content.
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
					modalContent.addEventListener('keydown', handleFocusTrap);

					// Cleanup focus trapping when the modal is closed.
					const closeButton = modal.querySelector(
						'.gatherpress--close-modal'
					);

					if (closeButton) {
						closeButton.addEventListener('click', () => {
							modal.classList.remove('gatherpress--is-visible');
							modalContent.removeEventListener(
								'keydown',
								handleFocusTrap
							);
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
