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
			const openModals = document.querySelectorAll(
				'.gatherpress--is-visible'
			);
			openModals.forEach((modal) => closeModal(modal));
		}
	};

	// Handle clicks outside modal content
	const handleOutsideClick = (event) => {
		const openModals = document.querySelectorAll(
			'.wp-block-gatherpress-modal.gatherpress--is-visible'
		);
		openModals.forEach((modal) => {
			const modalContent = modal.querySelector(
				'.wp-block-gatherpress-modal-content'
			);

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

const setupDropdownCloseHandlers = () => {
	// Function to close a dropdown
	const closeDropdown = (dropdown) => {
		dropdown.classList.remove('gatherpress--is-visible');
	};

	// Handle Escape key to close dropdowns
	const handleEscapeKey = (event) => {
		if (event.key === 'Escape') {
			const openDropdowns = document.querySelectorAll(
				'.wp-block-gatherpress-dropdown__menu.gatherpress--is-visible'
			);
			openDropdowns.forEach((dropdown) => closeDropdown(dropdown));
		}
	};

	// Handle clicks outside dropdown content
	const handleOutsideClick = (event) => {
		const openDropdowns = document.querySelectorAll(
			'.wp-block-gatherpress-dropdown__menu.gatherpress--is-visible'
		);
		openDropdowns.forEach((dropdown) => {
			const dropdownParent = dropdown.closest(
				'.wp-block-gatherpress-dropdown'
			);

			// Close dropdown if the click is outside the dropdown
			if (
				dropdownParent &&
				!dropdownParent.contains(event.target) // Click is NOT inside the dropdown parent
			) {
				closeDropdown(dropdown);
			}
		});
	};

	// Attach event listeners
	document.addEventListener('keydown', handleEscapeKey);
	document.addEventListener('click', handleOutsideClick);
};

domReady(() => {
	setupModalCloseHandlers();
	setupDropdownCloseHandlers();
});
