/**
 * Toggle to Show/Hide Calendar options.
 *
 * @param {TouchEvent} e Event.
 */
const addToCalendarToggle = (e) => {
	e.preventDefault();

	const currentListDisplay = e.target.nextElementSibling.style.display;
	const lists = document.querySelectorAll(
		'.wp-block-gatherpress-add-to-calendar__list'
	);

	for (let i = 0; i < lists.length; i++) {
		lists[i].style.display = 'none';
	}

	e.target.nextElementSibling.style.display =
		'none' === currentListDisplay ? 'flex' : 'none';
};

/**
 * Initialize all Add To Calendar blocks.
 */
const addToCalendarInit = () => {
	const containers = document.querySelectorAll(
		'.wp-block-gatherpress-add-to-calendar'
	);

	for (let i = 0; i < containers.length; i++) {
		containers[i]
			.querySelector('.wp-block-gatherpress-add-to-calendar__init')
			.addEventListener('click', addToCalendarToggle, false);

		document.addEventListener('click', ({ target }) => {
			if (!target.closest('.wp-block-gatherpress-add-to-calendar')) {
				containers[i].querySelector(
					'.wp-block-gatherpress-add-to-calendar__list'
				).style.display = 'none';
			}
		});
	}
};

document.addEventListener('DOMContentLoaded', () => {
	addToCalendarInit();
});
