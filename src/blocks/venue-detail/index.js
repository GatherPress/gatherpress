/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { mapMarker as icon } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import './editor.scss';
import edit from './edit';
import metadata from './block.json';
import variations from './variations';

/**
 * Register the Venue Detail block.
 *
 * @since 1.0.0
 */
registerBlockType( metadata.name, {
	icon,
	edit,
	variations,
} );
