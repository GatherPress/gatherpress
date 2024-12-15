/**
 * WordPress dependencies.
 */
import { store } from '@wordpress/interactivity';

store('gatherpress', {
	actions: {
		openModal(event) {
			event.preventDefault();

			const element = event.target;
			const modalManager = element.closest(
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

			const element = event.target;
			const modalManager = element.closest(
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
