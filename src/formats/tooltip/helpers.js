/**
 * Internal dependencies.
 */
import { DEFAULT_COLORS } from './constants';

/**
 * Get tooltip attributes from the active format.
 *
 * @param {Object} activeFormat The active format object.
 * @return {Object} Object containing tooltip, textColor, and bgColor.
 */
export function getTooltipAttributes( activeFormat ) {
	if ( ! activeFormat?.attributes ) {
		return {
			tooltip: '',
			textColor: DEFAULT_COLORS.textColor,
			bgColor: DEFAULT_COLORS.bgColor,
		};
	}

	return {
		tooltip: activeFormat.attributes[ 'data-gatherpress-tooltip' ] || '',
		textColor:
			activeFormat.attributes[ 'data-gatherpress-tooltip-text-color' ] ||
			DEFAULT_COLORS.textColor,
		bgColor:
			activeFormat.attributes[ 'data-gatherpress-tooltip-bg-color' ] ||
			DEFAULT_COLORS.bgColor,
	};
}
