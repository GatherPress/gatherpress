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
 * Edit component for the GatherPress RSVP Form block.
 *
 * This component renders the edit view of the GatherPress RSVP Form block.
 * The block allows visitors to RSVP to events without requiring a site account.
 * It provides a form interface for event registration and integrates with the
 * WordPress comment system for processing RSVP submissions.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component for editing the block.
 */
registerBlockType(metadata, {
	edit,
	save: () => {
		return (
			<div {...useBlockProps.save()}>
				<InnerBlocks.Content />
			</div>
		);
	},
});
