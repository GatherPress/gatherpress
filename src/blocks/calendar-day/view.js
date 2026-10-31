/**
 * Calendar Day Interactivity Store
 *
 * Updates today, past, and future classes on calendar day cells
 * based on the visitor's local browser time to support static page caches.
 *
 * @package
 */

import { store, getContext } from '@wordpress/interactivity';

/**
 * Returns today's date in YYYY-MM-DD format using browser local time.
 *
 * @return {string} YYYY-MM-DD string.
 */
function getBrowserToday() {
	const now = new Date();
	const year = now.getFullYear();
	const month = String( now.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( now.getDate() ).padStart( 2, '0' );
	return `${ year }-${ month }-${ day }`;
}

// Derived state: the server gives the same values from the site's date
// (Calendar_Day::register_day_state()), so the server render keeps them.
store( 'gatherpress/calendar-day', {
	state: {
		/**
		 * True if the cell's date matches today in the visitor's browser.
		 *
		 * @return {boolean} True if today.
		 */
		get isToday() {
			const ctx = getContext();
			const date = ctx?.date;
			if ( ! date ) {
				return false;
			}
			return date === getBrowserToday();
		},

		/**
		 * True if the cell's date is before today in the visitor's browser.
		 *
		 * @return {boolean} True if past.
		 */
		get isPast() {
			const ctx = getContext();
			const date = ctx?.date;
			if ( ! date ) {
				return false;
			}
			return date < getBrowserToday();
		},

		/**
		 * True if the cell's date is after today in the visitor's browser.
		 *
		 * @return {boolean} True if future.
		 */
		get isFuture() {
			const ctx = getContext();
			const date = ctx?.date;
			if ( ! date ) {
				return false;
			}
			return date > getBrowserToday();
		},

		/**
		 * Sets aria-current="date" for today, or false to remove the attribute.
		 *
		 * @return {string|false} 'date' or false.
		 */
		get ariaCurrent() {
			const ctx = getContext();
			const date = ctx?.date;
			return date && date === getBrowserToday() ? 'date' : false;
		},
	},
} );
