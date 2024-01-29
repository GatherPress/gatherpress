/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

/**
 * Toggle to Show/Hide Calendar options.
 *
 * @param {TouchEvent} e Event.
 */
const addToCalendarToggle = (e) => {
	e.preventDefault();

	const currentListDisplay = e.target.nextElementSibling.style.display;
	const lists = document.querySelectorAll('.gp-add-to-calendar__list');

	for (let i = 0; i < lists.length; i++) {
		lists[i].style.display = 'none';
	}

	e.target.nextElementSibling.style.display =
		'none' === currentListDisplay ? 'flex' : 'none';
};

/**
 * Initialize all Add To Calendar blocks.
 *
 * This function initializes the behavior of Add To Calendar blocks on the page.
 * It sets up event listeners for click and keydown events to toggle the display
 * of the calendar options list. The function targets elements with the class
 * 'gp-add-to-calendar' and adds event listeners to handle user interactions.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
const addToCalendarInit = () => {
	const containers = document.querySelectorAll('.gp-add-to-calendar');

	for (let i = 0; i < containers.length; i++) {
		containers[i]
			.querySelector('.gp-add-to-calendar__init')
			.addEventListener('click', addToCalendarToggle, false);

		document.addEventListener('click', ({ target }) => {
			if (!target.closest('.gp-add-to-calendar')) {
				containers[i].querySelector(
					'.gp-add-to-calendar__list'
				).style.display = 'none';
			}
		});

		document.addEventListener('keydown', ({ key }) => {
			if ('Escape' === key) {
				containers[i].querySelector(
					'.gp-add-to-calendar__list'
				).style.display = 'none';
			}
		});
	}
};

/**
 * Callback for when the DOM is ready.
 *
 * This callback function is executed when the DOM is fully loaded and ready for manipulation.
 * It calls the `addToCalendarInit` function to initialize the behavior of Add To Calendar blocks
 * on the page, setting up event listeners for user interactions.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady(() => {
	addToCalendarInit();
});
