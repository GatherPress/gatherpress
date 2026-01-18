/**
 * External dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

/**
 * Register the GatherPress Event List block.
 *
 * This code registers the GatherPress Event List block in the WordPress block editor.
 * It uses the block metadata from the 'block.json' file and associates it with the
 * edit component for rendering in the editor. The 'save' function is set to null as
 * the block doesn't have a front-end representation and is only used in the editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
