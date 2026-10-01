/* eslint-disable jsdoc/check-tag-names -- Jest reads @jest-environment from this block. */
/**
 * Tooltip view behavior in an environment with no document.
 *
 * The view script guards every reach for `document`, because the module is
 * evaluated wherever the bundle is loaded and nothing promises a DOM. Those
 * guards cannot be exercised from the jsdom environment the rest of the suite
 * runs in: `document` is defined there as a non-configurable global, so it can
 * neither be reassigned nor deleted. Running this one file under the node
 * environment is what makes `typeof document === 'undefined'` true honestly,
 * rather than faking it.
 *
 * Only the module body is exercised here. The handlers cannot run without a
 * DOM: they reach for the `Node` and `Element` globals, which the node
 * environment has no more than it has a document.
 *
 * @jest-environment node
 */
/* eslint-enable jsdoc/check-tag-names */

/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import { handleDocumentKeyDown } from '@src/formats/tooltip/view';

describe( 'Tooltip view without a document', () => {
	it( 'imports without touching a document that is not there', () => {
		// Reaching this line at all is the assertion: the module body runs on
		// import, and its initialization block would throw if it assumed a DOM.
		expect( typeof handleDocumentKeyDown ).toBe( 'function' );
	} );
} );
