/**
 * WordPress dependencies.
 */
import { store, getElement } from '@wordpress/interactivity';

store('gatherpress/modal', {
	actions: {
		openModal(event) {
			event.preventDefault();

			const element = getElement();
			const modalManager = element.ref.closest(
				'.wp-block-gatherpress-modal-manager'
			);

			if (modalManager) {
				const modal = modalManager.querySelector(
					'.wp-block-gatherpress-modal'
				);

				if (modal) {
					modal.classList.add('gatherpress--is-visible');
				}
			}
		},
		closeModal(event) {
			event.preventDefault();

			const element = getElement();
			const modalManager = element.ref.closest(
				'.wp-block-gatherpress-modal-manager'
			);

			if (modalManager) {
				const modal = modalManager.querySelector(
					'.wp-block-gatherpress-modal'
				);

				if (modal) {
					modal.classList.remove('gatherpress--is-visible');
				}
			}
		},
	},
});
