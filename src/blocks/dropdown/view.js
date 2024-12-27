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
				menu.classList.toggle('gatherpress--is-visible');
			}
		},
	},
});
