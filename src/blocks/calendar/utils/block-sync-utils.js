/**
 * Block tree navigation and localized name/label resolution helpers.
 *
 * @package
 * @since TBD
 */

import { __, _nx, _x, sprintf } from '@wordpress/i18n';

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
	name = __( 'Calendar', 'gatherpress' ),
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
 * Gets the pagination label for one unit.
 *
 * @param {boolean} isPrevious Whether the link goes back.
 * @param {string}  unit       'month' | 'week' | 'day'.
 * @return {string} Localized label (e.g. "Previous Month").
 */
function getSingleUnitPaginationLabel( isPrevious, unit ) {
	if ( isPrevious ) {
		return {
			month: _x( 'Previous Month', 'Calendar pagination label', 'gatherpress' ),
			week: _x( 'Previous Week', 'Calendar pagination label', 'gatherpress' ),
			day: _x( 'Previous Day', 'Calendar pagination label', 'gatherpress' ),
		}[ unit ];
	}

	return {
		month: _x( 'Next Month', 'Calendar pagination label', 'gatherpress' ),
		week: _x( 'Next Week', 'Calendar pagination label', 'gatherpress' ),
		day: _x( 'Next Day', 'Calendar pagination label', 'gatherpress' ),
	}[ unit ];
}

/**
 * Gets the pagination label for more than one unit.
 *
 * @param {boolean} isPrevious Whether the link goes back.
 * @param {string}  unit       'month' | 'week' | 'day'.
 * @param {number}  count      Number of units.
 * @return {string} Localized label (e.g. "Next 3 Months").
 */
function getMultiUnitPaginationLabel( isPrevious, unit, count ) {
	let label;

	if ( isPrevious ) {
		label = {
			/* translators: %d: Number of months. */
			month: _nx( 'Previous %d Month', 'Previous %d Months', count, 'Calendar pagination label', 'gatherpress' ),
			/* translators: %d: Number of weeks. */
			week: _nx( 'Previous %d Week', 'Previous %d Weeks', count, 'Calendar pagination label', 'gatherpress' ),
			/* translators: %d: Number of days. */
			day: _nx( 'Previous %d Day', 'Previous %d Days', count, 'Calendar pagination label', 'gatherpress' ),
		}[ unit ];
	} else {
		label = {
			/* translators: %d: Number of months. */
			month: _nx( 'Next %d Month', 'Next %d Months', count, 'Calendar pagination label', 'gatherpress' ),
			/* translators: %d: Number of weeks. */
			week: _nx( 'Next %d Week', 'Next %d Weeks', count, 'Calendar pagination label', 'gatherpress' ),
			/* translators: %d: Number of days. */
			day: _nx( 'Next %d Day', 'Next %d Days', count, 'Calendar pagination label', 'gatherpress' ),
		}[ unit ];
	}

	return sprintf( label, count );
}

/**
 * Resolves the pagination block label based on direction, viewType, and unitCount.
 * Omits the number when unitCount is 1 (e.g. "Previous Month", "Next 3 Months").
 *
 * Each label is one translatable sentence, so a language can put the words and
 * the number in its own order.
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
	const isPrevious = 'previous' === direction;
	const unit = [ 'week', 'day' ].includes( viewType ) ? viewType : 'month';

	return 1 < count
		? getMultiUnitPaginationLabel( isPrevious, unit, count )
		: getSingleUnitPaginationLabel( isPrevious, unit );
}
