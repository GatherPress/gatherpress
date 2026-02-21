/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

/**
 * Register the Venue Map block.
 *
 * @since 1.0.0
 */
registerBlockType( metadata.name, {
	edit,
} );
