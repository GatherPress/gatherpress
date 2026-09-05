/**
 * External dependencies
 */
import { describe, expect, it, beforeEach, jest } from '@jest/globals';

const NOTICE_CLASS = 'gatherpress-new-tab-notice';
const NOTICE_TEXT = '(opens in a new tab)';

/**
 * Load the script fresh against the current DOM.
 *
 * The module wires itself up on load, so each case builds its markup first
 * and then imports, the way the browser runs it after the page is parsed.
 *
 * @param {Object|undefined} config Value for the global PHP writes.
 *
 * @return {void}
 */
const load = ( config ) => {
	window.gatherPressNewTabNotice = config;

	jest.isolateModules( () => {
		require( '@src/helpers/new-tab-notice' );
	} );
};

/**
 * Let the MutationObserver deliver its records.
 *
 * @return {Promise<void>} Resolves once the queue has drained.
 */
const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

const config = {
	blockPrefix: 'wp-block-gatherpress-',
	noticeClass: NOTICE_CLASS,
	noticeText: NOTICE_TEXT,
};

describe( 'new-tab notice', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		delete window.gatherPressNewTabNotice;
	} );

	it( 'announces a link that is already on the page', () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_blank">Site</a></div>';

		load( config );

		const notice = document.querySelector( `.${ NOTICE_CLASS }` );

		expect( notice ).not.toBeNull();
		expect( notice.textContent ).toBe( NOTICE_TEXT );
		expect( notice.parentElement.tagName ).toBe( 'A' );
		expect( notice.className ).toBe( `screen-reader-text ${ NOTICE_CLASS }` );
	} );

	it( 'leaves a same-tab link alone', () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x">Site</a></div>';

		load( config );

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );

	it( 'ignores links outside GatherPress blocks', () => {
		document.body.innerHTML =
			'<div class="wp-block-paragraph"><a href="/x" target="_blank">Other</a></div>';

		load( config );

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );

	it( 'announces a link a block creates after render', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-online-event-link">' +
			'<span class="gatherpress-online-event__text">Online event</span></div>';

		load( config );

		const block = document.querySelector( '.wp-block-gatherpress-online-event-link' );
		const link = document.createElement( 'a' );

		link.href = 'https://example.com/meet';
		link.target = '_blank';
		link.textContent = 'Online event';
		block.replaceChild( link, block.firstElementChild );

		await settle();

		expect( link.querySelector( `.${ NOTICE_CLASS }` ) ).not.toBeNull();
	} );

	it( 'announces a link only once it opens in a new tab', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x">Site</a></div>';

		load( config );

		const link = document.querySelector( 'a' );

		expect( link.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();

		link.target = '_blank';

		await settle();

		expect( link.querySelector( `.${ NOTICE_CLASS }` ) ).not.toBeNull();
	} );

	it( 'does not announce a link twice', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_blank">Site' +
			`<span class="screen-reader-text ${ NOTICE_CLASS }">${ NOTICE_TEXT }</span>` +
			'</a></div>';

		load( config );

		const link = document.querySelector( 'a' );

		link.appendChild( document.createTextNode( ' ' ) );

		await settle();

		expect( link.querySelectorAll( `.${ NOTICE_CLASS }` ) ).toHaveLength( 1 );
	} );

	it( 'stands down when PHP wrote no configuration', () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_blank">Site</a></div>';

		load( undefined );

		expect( document.querySelector( '.undefined' ) ).toBeNull();
		expect( document.querySelector( 'a' ).children ).toHaveLength( 0 );
	} );

	it( 'waits for the document when the script runs during parsing', async () => {
		const readyState = jest
			.spyOn( document, 'readyState', 'get' )
			.mockReturnValue( 'loading' );

		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_blank">Site</a></div>';

		load( config );

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();

		readyState.mockRestore();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		await settle();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).not.toBeNull();
	} );

	it( 'stands down when the page has no GatherPress blocks', () => {
		document.body.innerHTML = '<a href="/x" target="_blank">Site</a>';

		load( config );

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );
} );
