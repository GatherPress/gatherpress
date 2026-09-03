/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Register the GatherPress Event Status block.
 *
 * @since 0.36.0
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
