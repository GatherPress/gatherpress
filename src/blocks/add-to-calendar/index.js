/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Edit component for the GatherPress Add to Calendar block.
 *
 * This component renders the edit view of the GatherPress Add to Calendar block.
 * The block allows users to add events to their personal calendars from the frontend.
 * It supports customization of display text and calendar options, and integrates
 * with event metadata provided by GatherPress.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component for editing the block.
 */
registerBlockType( metadata, {
	edit,
	save: () => {
		return (
			<div { ...useBlockProps.save() }>
				<InnerBlocks.Content />
			</div>
		);
	},
} );
