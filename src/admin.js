/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { getBlockType, unregisterBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from './helpers/misc';

/**
 * Remove unwanted blocks from localized array.
 */
domReady(() => {
	Object.keys(getFromGlobal('unregister_blocks')).forEach((key) => {
		const blockName = getFromGlobal('unregister_blocks')[key];

		if (blockName && 'undefined' !== typeof getBlockType(blockName)) {
			unregisterBlockType(blockName);
		}
	});
});
