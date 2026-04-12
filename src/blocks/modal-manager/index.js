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
 * Edit component for the GatherPress Modal Manager block.
 *
 * This component renders the edit view of the GatherPress Modal Manager block.
 * The block acts as a container for managing modals and their triggers.
 * It includes functionality for setting up modals and dynamically handling
 * their content and visibility, providing users with an interactive experience.
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
