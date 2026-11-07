/**
 * GatherPress Calendar Day Layout Filter
 *
 * @package
 * @since TBD
 */

import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { hasRibbonStyle } from '../calendar/utils/day-classes';

/**
 * Lock Layout controls for Calendar Day blocks using the Ribbon style.
 *
 * A calendar day with the Ribbon style requires its layout to stay intact
 * for multi-day ribbons and event slots.
 *
 * @since TBD
 *
 * @param {Object} settingValue Current setting value.
 * @param {string} settingName  Name of the setting being queried.
 * @param {string} clientId     Client ID of the block.
 * @param {string} blockName    Name of the block type.
 * @return {Object|boolean|undefined} Filtered setting value.
 */
export function filterCalendarDayLayoutSettings(
	settingValue,
	settingName,
	clientId,
	blockName,
) {
	if (
		'gatherpress/calendar-day' === blockName &&
		( 'layout' === settingName || 'layout.allowEditing' === settingName ) &&
		clientId
	) {
		const attributes = select( blockEditorStore )?.getBlockAttributes( clientId );
		if ( hasRibbonStyle( attributes?.className ) ) {
			if ( 'layout.allowEditing' === settingName ) {
				return false;
			}

			return {
				...( settingValue || {} ),
				allowEditing: false,
			};
		}
	}

	return settingValue;
}

addFilter(
	'blockEditor.useSetting.before',
	'gatherpress/calendar-day-layout',
	filterCalendarDayLayoutSettings,
);
