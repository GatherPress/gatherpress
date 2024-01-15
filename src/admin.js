/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { getBlockType, unregisterBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from './helpers/globals';

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
	Object.keys(getFromGlobal('unregister_blocks')).forEach((key) => {
		// Retrieve the block name using the key.
		const blockName = getFromGlobal('unregister_blocks')[key];

		// Check if the block name is defined and unregister the block.
		if (blockName && 'undefined' !== typeof getBlockType(blockName)) {
			unregisterBlockType(blockName);
		}
	});
});
