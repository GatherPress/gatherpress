/**
 * External dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Register the GatherPress Online Event v2 block.
 *
 * This block provides context-aware online event link fetching with support for
 * both event and venue contexts, with smart fallback logic.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
