/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';

import metadata from './block.json';

let postType = wp.data.select('core/editor').getCurrentPostType();

if ( 'gp_event' === postType ) {
	registerBlockType( metadata, {
		edit,
		save: () => null,
	} );
}
