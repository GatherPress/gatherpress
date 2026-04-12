/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import Edit from './edit';
import './style.scss';

registerBlockType( 'gatherpress/rsvp-template', {
	edit: Edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
} );
