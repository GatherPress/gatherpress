/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

const setupModalCloseHandlers = () => {
	// Function to close a modal
	const closeModal = (modal) => {
		modal.classList.remove('gatherpress--is-visible');
	};

	// Handle Escape key to close modals
	const handleEscapeKey = (event) => {
		if (event.key === 'Escape') {
			const openModals = document.querySelectorAll('.gatherpress--is-visible');
			openModals.forEach((modal) => closeModal(modal));
		}
	};

	// Handle clicks outside modal content
	const handleOutsideClick = (event) => {
		const openModals = document.querySelectorAll('.wp-block-gatherpress-modal.gatherpress--is-visible');
		openModals.forEach((modal) => {
			const modalContent = modal.querySelector('.wp-block-gatherpress-modal-content');

			// Close modal if the click is outside the modal content
			if (
				modal.contains(event.target) && // Click is inside the modal
				!modalContent.contains(event.target) // Click is NOT inside the modal content
			) {
				closeModal(modal);
			}
		});
	};

	// Attach event listeners
	document.addEventListener('keydown', handleEscapeKey);
	document.addEventListener('click', handleOutsideClick);
};

domReady(() => {
	setupModalCloseHandlers();
});
