/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

/**
 * Register the GatherPress Event Doors block.
 *
 * A dynamic block, rendered on the front end by `render.php`.
 *
 * @since TBD
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
