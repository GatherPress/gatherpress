/**
 * WordPress dependencies.
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Block transforms for the GatherPress Event Date block.
 *
 * Allows a core/post-date block to be converted into a gatherpress/event-date
 * block via the editor's "Transform to" menu, preserving the user's PHP date
 * format and text alignment.
 */
const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/post-date' ],
			transform: ( attributes ) => {
				const { format, textAlign } = attributes;
				const newAttributes = {
					displayType: 'start',
				};

				if ( format ) {
					newAttributes.startDateFormat = format;
				}

				if ( textAlign ) {
					newAttributes.textAlign = textAlign;
				}

				return createBlock(
					'gatherpress/event-date',
					newAttributes
				);
			},
		},
	],
};

export default transforms;
