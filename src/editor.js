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
import {
	InspectorAdvancedControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from './helpers/globals';
import './stores';
import BlockGuard from './components/BlockGuard';

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
						label={__('Post ID Override', 'gatherpress')}
						value={postId}
						onChange={(value) => {
							updateBlockAttributes(clientId, {
								postId: parseInt(value, 10) || 0,
							});
						}}
						help={__(
							'Specify the post ID of an event to replace the default post ID used by this block.',
							'gatherpress'
						)}
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

/**
 * Higher-Order Component to add BlockGuard functionality to supported GatherPress blocks.
 *
 * This HOC injects a toggle control into the Inspector Controls of blocks
 * that support `blockGuard`, enabling users to toggle block protection.
 * When enabled (default), an invisible overlay prevents interactions with inner blocks.
 *
 * @param {Function} BlockEdit - The original BlockEdit component.
 * @return {Function} Enhanced BlockEdit component with BlockGuard functionality.
 *
 * @example
 * // Usage:
 * // In a block's `block.json`, add the following to enable this feature:
 * {
 *   "supports": {
 *     "gatherpress": {
 *       "blockGuard": true
 *     }
 *   }
 * }
 */
const withBlockGuard = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, clientId } = props;

		// Check if the block supports `blockGuard`.
		if (
			!name.startsWith('gatherpress/') ||
			!getBlockType(name)?.supports?.gatherpress?.blockGuard
		) {
			return <BlockEdit {...props} />;
		}

		// Use state to track if BlockGuard is enabled (default to enabled)
		const [isBlockGuardEnabled, setIsBlockGuardEnabled] = useState(true);

		// Get block props which we'll spread to our wrapper
		const blockProps = useBlockProps();

		// This effect runs after render to apply the guard to inner blocks container
		useEffect(() => {
			if (!clientId) {
				return;
			}

			// Function to find and guard inner blocks
			const applyGuardToInnerBlocks = () => {
				// Try to find the inner blocks container within this block
				const blockNode = document.querySelector(
					`[data-block="${clientId}"]`
				);
				if (!blockNode) {
					return;
				}

				// Look for inner blocks container
				const innerBlocksContainer = blockNode.querySelector(
					'.block-editor-inner-blocks'
				);
				if (!innerBlocksContainer) {
					return;
				}

				// Set position relative on the container if not already
				if (
					getComputedStyle(innerBlocksContainer).position !==
					'relative'
				) {
					innerBlocksContainer.style.position = 'relative';
				}

				// Look for existing guard
				let guard =
					innerBlocksContainer.querySelector('.gp-block-guard');

				// If guard should be enabled
				if (isBlockGuardEnabled) {
					// Create guard if it doesn't exist
					if (!guard) {
						guard = document.createElement('div');
						guard.className = 'gp-block-guard';
						guard.style.position = 'absolute';
						guard.style.top = '0';
						guard.style.right = '0';
						guard.style.bottom = '0';
						guard.style.left = '0';
						guard.style.zIndex = '99';
						guard.style.cursor = 'pointer';
						guard.style.background = 'transparent';

						// Add click handler to select the parent block
						guard.addEventListener('mousedown', (e) => {
							// Always prevent default and stop propagation
							e.preventDefault();
							e.stopPropagation();

							// Force selection of this block
							dispatch('core/block-editor').selectBlock(clientId);
						});

						// Also prevent click events from propagating
						guard.addEventListener('click', (e) => {
							e.preventDefault();
							e.stopPropagation();
						});

						// Add a tabindex to make the guard focusable
						guard.setAttribute('tabindex', '0');

						// Add global keyboard event handler for the entire block
						const handleKeyDown = (e) => {
							if (
								(isBlockGuardEnabled &&
									document.activeElement === blockNode) ||
								blockNode.contains(document.activeElement)
							) {
								// Handle arrow keys to navigate between blocks
								if (
									[
										'ArrowDown',
										'ArrowUp',
										'ArrowLeft',
										'ArrowRight',
									].includes(e.key)
								) {
									e.preventDefault();
									e.stopPropagation();

									const blockEditor =
										select('core/block-editor');
									// Get all root-level blocks (not inner blocks)
									const rootBlocks = blockEditor.getBlocks();

									// Find the root block that contains our current block
									let rootClientId = clientId;
									let rootBlock =
										blockEditor.getBlock(clientId);

									// If this is an inner block, find its root parent
									while (
										blockEditor.getBlockRootClientId(
											rootClientId
										)
									) {
										rootClientId =
											blockEditor.getBlockRootClientId(
												rootClientId
											);
										rootBlock =
											blockEditor.getBlock(rootClientId);
									}

									// Get the index of the root block
									const rootIndex = rootBlocks.findIndex(
										(block) =>
											block.clientId === rootClientId
									);

									// Determine which block to navigate to based on arrow key
									if (
										e.key === 'ArrowDown' ||
										e.key === 'ArrowRight'
									) {
										// Navigate to next block if it exists
										if (rootIndex < rootBlocks.length - 1) {
											dispatch(
												'core/block-editor'
											).selectBlock(
												rootBlocks[rootIndex + 1]
													.clientId
											);
										}
									} else if (
										e.key === 'ArrowUp' ||
										e.key === 'ArrowLeft'
									) {
										// Navigate to previous block if it exists
										if (rootIndex > 0) {
											dispatch(
												'core/block-editor'
											).selectBlock(
												rootBlocks[rootIndex - 1]
													.clientId
											);
										}
									}
								}
							}
						};

						// Add the keydown handler to the document
						document.addEventListener(
							'keydown',
							handleKeyDown,
							true
						);

						// Store the handler reference to remove it later
						blockNode._blockGuardKeyHandler = handleKeyDown;

						innerBlocksContainer.appendChild(guard);
					}
				} else {
					// Remove guard if it exists
					if (guard) {
						guard.remove();
					}
				}
			};

			// Apply initially
			applyGuardToInnerBlocks();

			// Set up mutation observer to watch for changes in the DOM
			// This helps when blocks are initially rendered or updated
			const observer = new MutationObserver(() => {
				applyGuardToInnerBlocks();
			});

			// Start observing the document
			observer.observe(document.body, {
				childList: true,
				subtree: true,
			});

			// Clean up
			return () => {
				observer.disconnect();

				// Remove the keydown handler if it exists
				if (document.body._blockGuardKeyHandler) {
					document.removeEventListener(
						'keydown',
						document.body._blockGuardKeyHandler,
						true
					);
					delete document.body._blockGuardKeyHandler;
				}
			};
		}, [clientId, isBlockGuardEnabled]);

		return (
			<>
				<div {...blockProps}>
					<BlockEdit {...props} />
				</div>

				{/* Add Inspector Controls with the toggle */}
				<InspectorControls>
					<PanelBody>
						<ToggleControl
							label={__('Block Guard', 'gatherpress')}
							checked={isBlockGuardEnabled}
							onChange={setIsBlockGuardEnabled}
							help={
								isBlockGuardEnabled
									? __(
											'Block protection is enabled. Click to focus on parent block.',
											'gatherpress'
										)
									: __(
											'Block protection is disabled. Inner blocks can be freely edited.',
											'gatherpress'
										)
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withBlockGuard');

/**
 * Register the HOC as a filter for the BlockEdit component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
addFilter('editor.BlockEdit', 'gatherpress/with-block-guard', withBlockGuard);
