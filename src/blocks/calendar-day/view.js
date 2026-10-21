/**
 * GatherPress Calendar Day Interactivity Store
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

store( 'gatherpress/calendar-day', {
	callbacks: {
		/**
		 * True if the cell's date matches today in the visitor's browser.
		 *
		 * @return {boolean} True if today.
		 */
		isToday() {
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
		isPast() {
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
		isFuture() {
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
		ariaCurrent() {
			const ctx = getContext();
			const date = ctx?.date;
			return date && date === getBrowserToday() ? 'date' : false;
		},
	},
} );
