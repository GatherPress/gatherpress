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
 * Register the GatherPress Event Date block.
 *
 * This code registers the GatherPress Event Date block in the WordPress block editor.
 * It includes metadata from the 'block.json' file, defines the block styles with 'style.scss',
 * and specifies the 'edit' and 'save' components for the block. The 'edit' component is responsible
 * for the block's appearance and behavior in the editor, while the 'save' component defines how
 * the block should be rendered on the front end.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
