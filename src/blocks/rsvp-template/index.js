/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import Edit from './edit';

registerBlockType('gatherpress/rsvp-template', {
	edit: Edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
