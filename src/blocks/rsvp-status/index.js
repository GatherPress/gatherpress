/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';

registerBlockType(metadata, {
	edit: Edit,
	save: () => {
		// Use blockProps to ensure proper WordPress class handling
		const blockProps = useBlockProps.save();

		return <div {...blockProps}></div>;
	},
});
