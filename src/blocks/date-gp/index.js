import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import './editor.scss';

/**
 * Internal dependencies
 */
import edit from './edit';
import save from './save';
import json from './block.json';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerBlockType(json, {
	/**
	 * Used to construct a preview for the block to be shown in the block inserter.
	 */
	example: {
		attributes: {
			message: 'Put a date here, mofo!',
		},
	},

	edit,

	save,
});
