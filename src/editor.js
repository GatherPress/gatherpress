/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import {
	dispatch,
	select,
	subscribe,
	useSelect,
	useDispatch,
} from '@wordpress/data';
import { hasEventPastNotice, triggerEventCommunication } from './helpers/event';
import { getBlockType, unregisterBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorAdvancedControls } from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from './helpers/globals';
import './stores';

/**
 * Ensure Panels are Open for Events
 *
 * This script ensures that the editor sidebar is open in the WordPress block editor.
 * It uses the `domReady` function to ensure the DOM is ready before execution.
 * If the editor sidebar is not open, it opens the general sidebar, and displays a
 * notice for past events using the `hasEventPastNotice` function.
 *
 * @since 1.0.0
 */

// Execute the following code when the DOM is ready.
domReady(() => {
	const selectEditPost = select('core/edit-post');
	const dispatchEditPost = dispatch('core/edit-post');

	if (!selectEditPost || !dispatchEditPost) {
		return;
	}

	const isEditorSidebarOpened =
		selectEditPost.isEditorSidebarOpened('edit-post/document');

	if (!isEditorSidebarOpened) {
		dispatchEditPost.openGeneralSidebar('edit-post/document');
	}

	subscribe(triggerEventCommunication);

	hasEventPastNotice();
});

/**
 * Remove Unwanted Blocks
 *
 * This script removes unwanted blocks from the localized array.
 * It utilizes the `domReady` function to ensure the DOM is ready before execution.
 * It iterates through the keys of the 'unregister_blocks' array obtained from the global scope,
 * retrieves the block name, and unregisters the block using the `unregisterBlockType` function.
 *
 * @since 1.0.0
 */

// Execute the following code when the DOM is ready.
domReady(() => {
	// Iterate through keys of the 'unregister_blocks' array in the global scope.
	Object.keys(getFromGlobal('misc.unregisterBlocks')).forEach((key) => {
		// Retrieve the block name using the key.
		const blockName = getFromGlobal('misc.unregisterBlocks')[key];

		// Check if the block name is defined and unregister the block.
		if (blockName && 'undefined' !== typeof getBlockType(blockName)) {
			unregisterBlockType(blockName);
		}
	});
});

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
function addPostIdOverrideSupport(settings) {
	if (settings.supports?.gatherpress?.postIdOverride) {
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
	addPostIdOverrideSupport
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
const withPostIdOverride = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, clientId } = props;

		// Check if the block supports `postIdOverride`.
		if (
			!name.startsWith('gatherpress/') ||
			!getBlockType(name)?.supports?.gatherpress?.postIdOverride
		) {
			return <BlockEdit {...props} />;
		}

		const postId = useSelect(
			(blockEditorSelect) => {
				const { getBlockAttributes } =
					blockEditorSelect('core/block-editor');
				return getBlockAttributes(clientId)?.postId ?? '';
			},
			[clientId]
		);

		const { updateBlockAttributes } = useDispatch('core/block-editor');

		return (
			<>
				<BlockEdit {...props} />
				<InspectorAdvancedControls>
					<NumberControl
						label={__('Event ID Override', 'gatherpress')}
						value={postId}
						onChange={(value) => {
							updateBlockAttributes(clientId, {
								postId: parseInt(value, 10) || 0,
							});
						}}
					/>
				</InspectorAdvancedControls>
			</>
		);
	};
}, 'withPostIdOverride');

/**
 * Register the HOC as a filter for the BlockEdit component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
addFilter(
	'editor.BlockEdit',
	'gatherpress/with-post-id-override',
	withPostIdOverride
);
