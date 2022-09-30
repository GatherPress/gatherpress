import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
/**
 * Internal dependencies
 */
import edit from './edit';
import save from './save';



/**
 * Block Registration
 */

registerBlockType( metadata, {
	icon: {
		src: 'location',
		foreground: '#1DB954',
		background: '#dedede'
	},
	edit,
	save,
});
