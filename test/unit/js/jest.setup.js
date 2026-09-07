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
