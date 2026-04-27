/**
 * External dependencies.
 */
import { describe, expect, it, beforeEach, afterEach } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	toCamelCase,
	stripScriptsAndEventHandlers,
	getUrlParam,
} from '@src/helpers/globals';

/**
 * Coverage for stripScriptsAndEventHandlers.
 *
 * The function is a narrow defense-in-depth helper, NOT a general HTML
 * sanitizer. The "removes ..." cases below cover what it strips; the
 * "does NOT strip ..." cases lock in the intentional gaps so a future
 * contributor doesn't mistake this helper for safe-by-default HTML
 * sanitization. Untrusted HTML still needs DOMPurify or equivalent.
 */
describe( 'stripScriptsAndEventHandlers', () => {
	it( 'removes script tags from HTML', () => {
		const html = '<div>Safe content<script>alert("xss");</script></div>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).not.toContain( '<script>' );
		expect( sanitized ).toContain( '<div>Safe content</div>' );
	} );

	it( 'removes onclick attributes from HTML elements', () => {
		const html = '<button onclick="alert(\'xss\')">Click me</button>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).not.toContain( 'onclick' );
		expect( sanitized ).toContain( '<button>Click me</button>' );
	} );

	it( 'removes multiple on* event handlers from HTML elements', () => {
		const html =
			'<div onmouseover="alert(1)" onload="alert(2)" onclick="alert(3)">Test</div>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).not.toContain( 'onmouseover' );
		expect( sanitized ).not.toContain( 'onload' );
		expect( sanitized ).not.toContain( 'onclick' );
		expect( sanitized ).toContain( '<div>Test</div>' );
	} );

	it( 'handles nested elements with unsafe attributes', () => {
		const html =
			'<div><p onclick="bad()">Text</p><span onmouseover="evil()">More</span></div>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).not.toContain( 'onclick' );
		expect( sanitized ).not.toContain( 'onmouseover' );
		expect( sanitized ).toContain( '<div><p>Text</p><span>More</span></div>' );
	} );

	it( 'handles nested script tags', () => {
		const html =
			'<div>Start<script>bad code</script>Middle<script>more bad</script>End</div>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).not.toContain( '<script>' );
		expect( sanitized ).toContain( '<div>StartMiddleEnd</div>' );
	} );

	it( 'preserves safe HTML content and attributes', () => {
		const html =
			'<a href="https://example.com" target="_blank" class="link">Safe Link</a>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).toContain( 'href="https://example.com"' );
		expect( sanitized ).toContain( 'target="_blank"' );
		expect( sanitized ).toContain( 'class="link"' );
		expect( sanitized ).toContain( '>Safe Link</a>' );
	} );

	it( 'handles empty input', () => {
		expect( stripScriptsAndEventHandlers( '' ) ).toBe( '' );
	} );

	it( 'handles plain text without HTML', () => {
		const text = 'Just some plain text without any HTML';

		expect( stripScriptsAndEventHandlers( text ) ).toBe( text );
	} );

	it( 'handles malformed HTML gracefully', () => {
		const malformed = '<div>Unclosed div<script>alert("bad");</script>';
		const sanitized = stripScriptsAndEventHandlers( malformed );

		expect( sanitized ).not.toContain( '<script>' );
		expect( sanitized ).toContain( '<div>Unclosed div' );
	} );

	it( 'handles script elements that are already detached', () => {
		// Create a document with a script element.
		const html = '<div id="container"><script>bad();</script></div>';

		// Mock getElementsByTagName to return an element without a parentNode.
		const originalCreateHTMLDocument =
			document.implementation.createHTMLDocument;
		document.implementation.createHTMLDocument = function( title ) {
			const doc = originalCreateHTMLDocument.call( this, title );
			const originalGetElementsByTagName =
				doc.body.getElementsByTagName;

			// Override getElementsByTagName to test the defensive check.
			doc.body.getElementsByTagName = function( tagName ) {
				const elements = originalGetElementsByTagName.call(
					this,
					tagName,
				);

				// Create a detached script element to test the parentNode check.
				if ( 0 < elements.length ) {
					const detachedScript = doc.createElement( 'script' );
					detachedScript.textContent = 'detached();';

					// Create a new collection that includes the detached element.
					const newCollection = Array.from( elements );
					newCollection.push( detachedScript );

					return newCollection;
				}

				return elements;
			};

			return doc;
		};

		// This should not throw even with a detached script element.
		const sanitized = stripScriptsAndEventHandlers( html );

		// Restore original implementation.
		document.implementation.createHTMLDocument =
			originalCreateHTMLDocument;

		expect( sanitized ).not.toContain( '<script>' );
		expect( sanitized ).toContain( '<div' );
	} );

	// The following cases lock in what the helper is INTENTIONALLY NOT
	// doing. If any of them flips to "stripped", that's a behavior change
	// that needs deliberate review and a docblock update — not a quiet
	// improvement.

	it( 'does NOT strip javascript: URLs', () => {
		const html = '<a href="javascript:alert(1)">click</a>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).toContain( 'javascript:alert(1)' );
	} );

	it( 'does NOT strip data: URIs with executable payloads', () => {
		const html =
			'<iframe src="data:text/html,<script>alert(1)</script>"></iframe>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).toContain( 'data:text/html' );
	} );

	it( 'does NOT strip iframe / object / embed elements', () => {
		const html =
			'<iframe src="https://evil.test"></iframe><object data="x"></object><embed src="y">';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).toContain( '<iframe' );
		expect( sanitized ).toContain( '<object' );
		expect( sanitized ).toContain( '<embed' );
	} );

	it( 'does NOT strip srcdoc / formaction attributes', () => {
		const html =
			'<iframe srcdoc="<script>alert(1)</script>"></iframe>' +
			'<button formaction="javascript:alert(1)">x</button>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).toContain( 'srcdoc' );
		expect( sanitized ).toContain( 'formaction' );
	} );

	it( 'does NOT strip inline style attributes', () => {
		const html =
			'<div style="background:url(javascript:alert(1))">x</div>';
		const sanitized = stripScriptsAndEventHandlers( html );

		expect( sanitized ).toContain( 'style=' );
	} );
} );

/**
 * Coverage for toCamelCase.
 */
describe( 'toCamelCase', () => {
	it( 'converts a simple snake_case string to camelCase', () => {
		expect( toCamelCase( 'hello_world' ) ).toBe( 'helloWorld' );
	} );

	it( 'converts a multi-word snake_case string to camelCase', () => {
		expect( toCamelCase( 'not_attending_the_event' ) ).toBe(
			'notAttendingTheEvent',
		);
	} );

	it( 'handles strings that are already camelCase', () => {
		expect( toCamelCase( 'helloWorld' ) ).toBe( 'helloWorld' );
	} );

	it( 'handles single word strings without underscores', () => {
		expect( toCamelCase( 'hello' ) ).toBe( 'hello' );
	} );

	it( 'handles empty strings', () => {
		expect( toCamelCase( '' ) ).toBe( '' );
	} );

	it( 'handles strings with consecutive underscores', () => {
		expect( toCamelCase( 'hello__world' ) ).toBe( 'helloWorld' );
	} );

	it( 'handles strings with three or more consecutive underscores', () => {
		expect( toCamelCase( 'hello___world' ) ).toBe( 'helloWorld' );
	} );

	it( 'preserves uppercase letters after underscores', () => {
		expect( toCamelCase( 'hello_World' ) ).toBe( 'helloWorld' );
	} );

	it( 'handles uppercase letters in the middle of words', () => {
		expect( toCamelCase( 'heLLo_world' ) ).toBe( 'heLLoWorld' );
	} );
} );

/**
 * Coverage for getUrlParam.
 */
describe( 'getUrlParam', () => {
	let OriginalURLSearchParams;

	beforeEach( () => {
		OriginalURLSearchParams = global.URLSearchParams;
	} );

	afterEach( () => {
		global.URLSearchParams = OriginalURLSearchParams;
	} );

	/**
	 * Helper to mock URLSearchParams for a given search string.
	 *
	 * @param {string} search - The search string to mock.
	 */
	function mockLocationSearch( search ) {
		global.URLSearchParams = class extends OriginalURLSearchParams {
			constructor() {
				super( search );
			}
		};
	}

	it( 'returns parameter value when parameter exists', () => {
		mockLocationSearch( '?foo=bar&baz=qux' );

		expect( getUrlParam( 'foo' ) ).toBe( 'bar' );
		expect( getUrlParam( 'baz' ) ).toBe( 'qux' );
	} );

	it( 'returns null when parameter does not exist', () => {
		mockLocationSearch( '?foo=bar' );

		expect( getUrlParam( 'missing' ) ).toBeNull();
	} );

	it( 'handles empty query string', () => {
		mockLocationSearch( '' );

		expect( getUrlParam( 'anything' ) ).toBeNull();
	} );

	it( 'handles URL-encoded values', () => {
		mockLocationSearch( '?message=hello%20world' );

		expect( getUrlParam( 'message' ) ).toBe( 'hello world' );
	} );

	it( 'handles parameters with no value', () => {
		mockLocationSearch( '?flag' );

		expect( getUrlParam( 'flag' ) ).toBe( '' );
	} );

	it( 'handles multiple parameters with same name', () => {
		mockLocationSearch( '?tag=react&tag=wordpress' );

		// URLSearchParams.get() returns the first value.
		expect( getUrlParam( 'tag' ) ).toBe( 'react' );
	} );

	it( 'handles parameters with special characters', () => {
		mockLocationSearch(
			'?email=test%40example.com&path=%2Fhome%2Fuser'
		);

		expect( getUrlParam( 'email' ) ).toBe( 'test@example.com' );
		expect( getUrlParam( 'path' ) ).toBe( '/home/user' );
	} );
} );
