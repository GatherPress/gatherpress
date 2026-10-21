/**
 * Core calendar calculation and configuration utilities.
 *
 * @package
 * @since 0.1.0
 */

import { dateI18n, getSettings } from '@wordpress/date';
import { select } from '@wordpress/data';

// Re-export decomposed helpers so existing imports remain backward-compatible:
export * from './grid-builder';
export * from './block-sync-utils';

export const WEEKDAY_SLUGS = [
	'sunday',
	'monday',
	'tuesday',
	'wednesday',
	'thursday',
	'friday',
	'saturday',
];

/**
 * Retrieves the start of week setting, syncing directly with PHP's setting.
 *
 * @return {number} Start of week index (0 = Sunday, 1 = Monday).
 */
export function getStartOfWeek() {
	const settings = select( 'core/editor' )?.getEditorSettings?.();
	const wpStartOfWeek = Number( settings?.gatherpress?.startOfWeek );
	if ( ! Number.isNaN( wpStartOfWeek ) ) {
		return wpStartOfWeek;
	}
	const dateSettings = getSettings();
	return Number( dateSettings?.l10n?.startOfWeek ?? 0 );
}

/**
 * Retrieves the days of the week considered weekend days in the editor.
 * Defaults to [ 0, 6 ] (Sunday and Saturday).
 *
 * Cultural Context:
 * - Western standard: Saturday (6) and Sunday (0).
 * - Middle East & North Africa (e.g. Egypt, Saudi Arabia): Friday (5) and Saturday (6).
 * - Israel: Friday (5) and Saturday (6).
 * - Iran: Friday (5) only.
 * - Nepal: Saturday (6) only.
 *
 * @return {number[]} Array of weekend day integers (0 = Sunday, 6 = Saturday).
 */
export function getWeekendDays() {
	const settings = select( 'core/editor' )?.getEditorSettings?.();
	return Array.isArray( settings?.gatherpress?.weekendDays )
		? settings.gatherpress.weekendDays
		: [ 0, 6 ];
}

/**
 * Checks whether a day of the week is considered a weekend day.
 *
 * @param {number} dayOfWeek Day of the week integer (0-6).
 * @return {boolean} True if weekend.
 */
export function isWeekendDay( dayOfWeek ) {
	return getWeekendDays().includes( dayOfWeek );
}

/**
 * Retrieves the maximum number of posts to query for calendar display.
 *
 * @return {number} Maximum number of posts to query.
 */
export function getPostsPerPage() {
	const settings = select( 'core/editor' )?.getEditorSettings?.();
	const postsPerPage = Number( settings?.gatherpress?.postsPerPage );

	return ! Number.isNaN( postsPerPage ) && 0 < postsPerPage
		? postsPerPage
		: 500;
}

/**
 * Computes table grid column count based on view type, weekend visibility, and time grid gutter.
 *
 * @param {string}  viewType      View type ('month', 'week', 'day').
 * @param {boolean} showWeekends  Whether weekends are shown.
 * @param {number}  unitCount     Number of units to show.
 * @param {boolean} hasTimeGutter Whether the time gutter column is active.
 *
 * @return {number} Total grid column count.
 */
export function getColumnsCount(
	viewType = 'month',
	showWeekends = true,
	unitCount = 1,
	hasTimeGutter = false,
) {
	let columns = 7;

	if ( 'day' === viewType ) {
		columns = Math.max( 1, Number( unitCount ) || 1 );
	} else {
		const workdayCount = 7 - getWeekendDays().length;
		columns = showWeekends ? 7 : workdayCount;
	}

	// Only Day and Week views can have a time gutter
	if ( hasTimeGutter && ( 'day' === viewType || 'week' === viewType ) ) {
		columns += 1;
	}

	return columns;
}

/**
 * Get day names based on start of week setting.
 *
 * Uses WordPress dateI18n to get properly localized day names that respect
 * the site's language settings. The order of days is adjusted based on the
 * start_of_week option (e.g., Monday-first vs Sunday-first).
 *
 * How it works:
 * 1. Start with a known Sunday (2024-01-07)
 * 2. For each day of the week, calculate which day it should be based on startOfWeek
 * 3. Use dateI18n with 'D' format to get the translated abbreviated day name
 *
 * @since 0.1.0
 *
 * @param {number}  startOfWeek  - Start of week (0=Sunday, 1=Monday, 2=Tuesday, etc.).
 * @param {boolean} showWeekends - Whether to include weekend days.
 *
 * @return {Array<string>} Array of day name labels in the correct order.
 *
 * @example
 * // Sunday-first week (US style)
 * getDayNames(0) // Returns ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
 *
 * @example
 * // Monday-first week (European style)
 * getDayNames(1) // Returns ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
 */
export function getDayNames( startOfWeek = 0, showWeekends = true ) {
	const days = [];

	// Base date: 2024-01-07 is a Sunday (day 0).
	// We use a fixed date at noon UTC and format in UTC so timezone shifts
	// never change the day name.
	const baseSunday = new Date( '2024-01-07T12:00:00Z' );

	for ( let i = 0; 7 > i; i++ ) {
		// Calculate the day of week (0=Sunday, 6=Saturday).
		// The modulo ensures we wrap around (e.g., day 7 becomes day 0).
		const dayOfWeek = ( startOfWeek + i ) % 7;

		if ( ! showWeekends && isWeekendDay( dayOfWeek ) ) {
			continue;
		}

		// Create a date for this day of week by adding days to base Sunday in UTC.
		const dayDate = new Date( baseSunday );
		dayDate.setUTCDate( baseSunday.getUTCDate() + dayOfWeek );

		// Get the abbreviated day name using dateI18n for proper localization.
		// 'D' format returns the abbreviated day name (e.g., 'Mon', 'Tue', etc.).
		// This respects the site's language setting via WordPress core.
		// Format in UTC for proper localization without site timezone shift.
		days.push( dateI18n( 'D', dayDate, 'UTC' ) );
	}

	return days;
}

/**
 * Generate the twelve month options for the month picker.
 *
 * @since 0.1.0
 *
 * @return {Array<Object>} Array of month options, each containing:
 *   - {string} value - Month number in format "MM"
 *   - {string} label - Localized month name (e.g., "January")
 */
export function generateMonthOptions() {
	const options = [];

	for ( let month = 1; 12 >= month; month++ ) {
		// The 15th at noon: a timezone offset cannot move it to another month.
		const date = new Date( 2024, month - 1, 15, 12, 0, 0 );
		options.push( {
			value: String( month ).padStart( 2, '0' ),
			label: dateI18n( 'F', date ),
		} );
	}

	return options;
}
