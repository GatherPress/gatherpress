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
				}
			}
		},
		openModalKeyHandler(event) {
			if ('Enter' === event.key || ' ' === event.key) {
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
