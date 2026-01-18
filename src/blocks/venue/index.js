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
 * Register the GatherPress Venue block.
 *
 * This code registers the GatherPress Venue block in the WordPress block editor.
 * It uses the block metadata from the 'block.json' file and associates it with the
 * edit component for rendering in the editor. The 'save' function is set to an empty
 * function as the block saves its content in the edit component.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit,
	save: () => {},
} );
