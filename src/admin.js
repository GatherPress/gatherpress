/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { getBlockType, unregisterBlockType } from '@wordpress/blocks';

/**
 * Remove unwanted blocks from localized array.
 */
domReady(
	() => {
		// eslint-disable-next-line no-undef
		Object.keys( GatherPress.unregister_blocks ).forEach(
			(key) => {
				// eslint-disable-next-line no-undef
				const blockName = GatherPress.unregister_blocks[key];
				if (blockName && 'undefined' !== typeof getBlockType( blockName )) {
					unregisterBlockType( blockName );
				}
			}
		);
	}
);
