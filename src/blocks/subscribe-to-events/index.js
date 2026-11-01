/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './style.scss';
import edit from './edit';
import metadata from './block.json';

/**
 * Register the Subscribe to Events block.
 *
 * The frontend markup comes from render.php; the editor preview renders the
 * same PHP through ServerSideRender so both agree on the resolved feed URL.
 *
 * @since TBD
 */
registerBlockType( metadata.name, {
	icon: 'calendar',
	edit,
} );
