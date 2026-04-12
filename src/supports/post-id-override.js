/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorAdvancedControls } from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

/**
 * Adds `postIdOverride` support to GatherPress blocks.
 *
 * This function modifies block settings during block registration to include
 * a `postId` attribute for blocks that declare support for `postIdOverride` in
 * their `supports` configuration.
 *
 * @param {Object} settings Original block settings.
 * @return {Object} Updated block settings with `postId` attribute if `postIdOverride` is supported.
 */
function addPostIdOverrideSupport( settings ) {
	if ( settings.supports?.gatherpress?.postIdOverride ) {
		settings.attributes = {
			...settings.attributes,
			postId: {
				type: 'number',
			},
		};
	}

	return settings;
}

/**
 * Registers a filter to extend block settings during block registration.
 *
 * This filter applies the `addPostIdOverrideSupport` function to all blocks,
 * adding the `postId` attribute to blocks that support `postIdOverride`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#blocks-registerblocktype
 */
addFilter(
	'blocks.registerBlockType',
	'gatherpress/add-post-id-override-support',
	addPostIdOverrideSupport,
);

/**
 * Higher-Order Component to add a postId override control to supported GatherPress blocks.
 *
 * This HOC injects a `NumberControl` into the advanced settings panel of blocks
 * that support `postIdOverride`, enabling users to specify a custom post ID.
 *
 * @param {Function} BlockEdit - The original BlockEdit component.
 * @return {Function} Enhanced BlockEdit component with postId override functionality.
 *
 * @example
 * // Usage:
 * // In a block's `block.json`, add the following to enable this feature:
 * {
 *   "supports": {
 *     "gatherpress": {
 *       "postIdOverride": true
 *     }
 *   }
 * }
 */
const withPostIdOverride = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, clientId } = props;

		// Check if the block supports `postIdOverride`.
		if (
			! name.startsWith( 'gatherpress/' ) ||
			! getBlockType( name )?.supports?.gatherpress?.postIdOverride
		) {
			return <BlockEdit { ...props } />;
		}

		const postId = useSelect(
			( blockEditorSelect ) => {
				const { getBlockAttributes } =
					blockEditorSelect( 'core/block-editor' );
				return getBlockAttributes( clientId )?.postId ?? '';
			},
			[ clientId ],
		);

		const { updateBlockAttributes } = useDispatch( 'core/block-editor' );

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorAdvancedControls>
					<NumberControl
						label={ __( 'Post ID Override', 'gatherpress' ) }
						value={ postId }
						onChange={ ( value ) => {
							updateBlockAttributes( clientId, {
								postId: parseInt( value, 10 ) || '',
							} );
						} }
						help={ __(
							'Specify the post ID of an event to replace the default post ID used by this block.',
							'gatherpress',
						) }
					/>
				</InspectorAdvancedControls>
			</>
		);
	};
}, 'withPostIdOverride' );

/**
 * Register the HOC as a filter for the BlockEdit component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
addFilter(
	'editor.BlockEdit',
	'gatherpress/with-post-id-override',
	withPostIdOverride,
);
