/**
 * Globals jsdom does not provide.
 *
 * `@wordpress/core-data` reaches `@wordpress/sync`, whose polling manager
 * uses `TextEncoder` at import time. Node has had it since 11, but jsdom
 * does not expose it on the test environment's global, so importing the
 * store throws `ReferenceError: TextEncoder is not defined`.
 */
const { TextEncoder, TextDecoder } = require( 'node:util' );

if ( 'undefined' === typeof global.TextEncoder ) {
	global.TextEncoder = TextEncoder;
}

if ( 'undefined' === typeof global.TextDecoder ) {
	global.TextDecoder = TextDecoder;
}

/**
 * jsdom has no `<dialog>` methods. These stand in for them closely enough
 * for the modal tests: `showModal()` sets `open` and focuses the first
 * focusable element, as the browser's dialog focusing steps do, and
 * `close()` removes `open`.
 */
const DIALOG_FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

if ( 'function' !== typeof window.HTMLDialogElement.prototype.showModal ) {
	window.HTMLDialogElement.prototype.showModal = function() {
		this.setAttribute( 'open', '' );
		this.querySelector( DIALOG_FOCUSABLE )?.focus();
	};

	window.HTMLDialogElement.prototype.close = function() {
		this.removeAttribute( 'open' );
	};
}
