/**
 * Hook to retrieve and stabilize the calendar-week and calendar-day template blocks.
 *
 * @package
 * @since TBD
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import { useStableValue } from '../../../utils/use-stable-value';

/**
 * Returns stable references to the week and day template blocks inside the calendar.
 *
 * @param {string} clientId Calendar block client ID.
 * @return {Object} Day template blocks and attributes.
 */
export function useCalendarDayTemplate( clientId ) {
	return useStableValue(
		useSelect(
			( select ) => {
				const { getBlocks } = select( blockEditorStore );
				const weekBlock = getBlocks( clientId )[ 0 ];
				const dayBlock = weekBlock
					? getBlocks( weekBlock.clientId )[ 0 ]
					: null;

				return {
					dayInnerBlocks: dayBlock
						? getBlocks( dayBlock.clientId )
						: [],
					dayBlockAttributes: dayBlock?.attributes ?? {},
					weekBlockAttributes: weekBlock?.attributes ?? {},
				};
			},
			[ clientId ],
		),
	);
}
