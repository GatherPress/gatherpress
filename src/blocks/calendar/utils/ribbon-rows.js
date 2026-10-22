/**
 * Ribbon row style for the calendar week and day blocks.
 *
 * @package
 * @since TBD
 */

/**
 * Build the style properties for the ribbon rows of a week or day.
 *
 * Mirrors Calendar_Week::get_ribbon_rows_style(). The time grid CSS takes the
 * ribbon rows from --gatherpress-ribbon-rows, which is left out for 0:
 * repeat() with a count of 0 is not valid CSS and would drop the whole row
 * template.
 *
 * @since TBD
 *
 * @param {number} numRibbonSlots Number of ribbon rows.
 *
 * @return {Object} Style properties for a React style object.
 */
export function getRibbonRowsStyle( numRibbonSlots ) {
	const count = Math.max( 0, Math.floor( Number( numRibbonSlots ) ) || 0 );

	return {
		'--gatherpress-num-ribbon-slots': count,
		...( 0 < count
			? { '--gatherpress-ribbon-rows': `repeat(${ count }, 24px)` }
			: {} ),
	};
}
