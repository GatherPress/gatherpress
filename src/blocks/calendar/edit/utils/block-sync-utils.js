/**
 * Block tree navigation and localized name/label resolution helpers.
 *
 * @package
 * @since TBD
 */

import { __, _n, _x, sprintf } from '@wordpress/i18n';

/**
 * Recursively find a block by name within an array of blocks.
 *
 * @param {Array}  blocks    Array of parsed blocks.
 * @param {string} blockName Block name to search for.
 * @return {Object|null} Matching block or null.
 */
export function findBlockByName( blocks = [], blockName ) {
	for ( const block of blocks ) {
		if ( block.name === blockName ) {
			return block;
		}
		if ( block.innerBlocks && block.innerBlocks.length ) {
			const found = findBlockByName( block.innerBlocks, blockName );
			if ( found ) {
				return found;
			}
		}
	}
	return null;
}

/**
 * Recursively find the block bound to the calendar heading source.
 *
 * @param {Array} blocks Array of parsed blocks to search.
 * @return {Object|null} Matching heading block or null.
 */
export function findHeadingBlock( blocks = [] ) {
	for ( const block of blocks ) {
		const source = block.attributes?.metadata?.bindings?.content?.source;
		if ( 'gatherpress/calendar-heading' === source ) {
			return block;
		}
		if ( block.innerBlocks && block.innerBlocks.length ) {
			const found = findHeadingBlock( block.innerBlocks );
			if ( found ) {
				return found;
			}
		}
	}
	return null;
}

/**
 * Resolves the calendar block name based on viewType and unitCount.
 *
 * @param {string} viewType  View type: 'month' | 'week' | 'day'.
 * @param {number} unitCount Number of units.
 * @param {string} name      Block name.
 * @return {string} Formatted name (e.g. "Month Calendar" or "3 Month Calendar").
 */
export function getCalendarBlockName(
	viewType = 'month',
	unitCount = 1,
	name = 'Calendar',
) {
	const count = Number( unitCount ) || 1;

	const viewLabels = {
		month: __( 'Month', 'gatherpress' ),
		week: __( 'Week', 'gatherpress' ),
		day: __( 'Day', 'gatherpress' ),
	};

	const label = viewLabels[ viewType ] || viewLabels.month;

	if ( 1 < count ) {
		return sprintf(
			/* translators: %1$d: unit count, %2$s: view type label (Month, Week, Day), %3$s: block name. */
			_x(
				'%1$d %2$s %3$s',
				'Calendar block name',
				'gatherpress',
			),
			count,
			label,
			name,
		);
	}

	return sprintf(
		/* translators: %1$s: view type label (Month, Week, Day), %2$s: block name. */
		_x( '%1$s %2$s', 'Calendar block name', 'gatherpress' ),
		label,
		name,
	);
}

/**
 * Resolves the pagination container block name in List View.
 *
 * @param {string} viewType  'month' | 'week' | 'day'.
 * @param {number} unitCount Number of units.
 * @return {string} Localized block name (e.g. "Month Pagination", "3 Month Pagination").
 */
export function getPaginationContainerName(
	viewType = 'month',
	unitCount = 1,
) {
	return getCalendarBlockName(
		viewType,
		unitCount,
		_x( 'Pagination', 'Pagination block name', 'gatherpress' ),
	);
}

/**
 * Resolves the pagination block label based on direction, viewType, and unitCount.
 * Omits the number when unitCount is 1 (e.g. "Previous Month", "Next 3 Months").
 *
 * @param {string} direction 'previous' | 'next'.
 * @param {string} viewType  'month' | 'week' | 'day'.
 * @param {number} unitCount Number of units.
 * @return {string} Formatted label (e.g. "Previous Month", "Next 3 Months").
 */
export function getPaginationLabel(
	direction = 'next',
	viewType = 'month',
	unitCount = 1,
) {
	const count = Number( unitCount ) || 1;

	const directionLabels = {
		next: __( 'Next', 'gatherpress' ),
		previous: __( 'Previous', 'gatherpress' ),
	};

	const unitLabels = {
		month: _n( 'Month', 'Months', count, 'gatherpress' ),
		week: _n( 'Week', 'Weeks', count, 'gatherpress' ),
		day: _n( 'Day', 'Days', count, 'gatherpress' ),
	};

	const directionLabel = directionLabels[ direction ] || directionLabels.next;
	const unitLabel = unitLabels[ viewType ] || unitLabels.month;

	if ( 1 < count ) {
		return sprintf(
			/* translators: %1$s: direction label (Next, Previous), %2$d: unit count, %3$s: unit label (Months, Weeks, Days). */
			_x( '%1$s %2$d %3$s', 'Pagination label', 'gatherpress' ),
			directionLabel,
			count,
			unitLabel,
		);
	}

	return sprintf(
		/* translators: %1$s: direction label (Next, Previous), %2$s: unit label (Month, Week, Day). */
		_x( '%1$s %2$s', 'Pagination label', 'gatherpress' ),
		directionLabel,
		unitLabel,
	);
}
