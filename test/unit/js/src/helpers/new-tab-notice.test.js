/**
 * External dependencies
 */
import { describe, expect, it, beforeEach, jest } from '@jest/globals';

/**
 * Internal dependencies
 */
import { initTooltips } from '@src/formats/tooltip/view';

const NOTICE_CLASS = 'gatherpress-new-tab-notice';
const NOTICE_TEXT = '(opens in a new tab)';

/**
 * Load the script fresh against the current DOM.
 *
 * The module wires itself up on load, so each case builds its markup first
 * and then imports, the way the browser runs it after the page is parsed.
 *
 * @return {void}
 */
const load = () => {
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

describe( 'new-tab notice', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'announces a link that is already on the page', () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_blank">Site</a></div>';

		load();

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

		load();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );

	it( 'ignores links outside GatherPress blocks', () => {
		document.body.innerHTML =
			'<div class="wp-block-paragraph"><a href="/x" target="_blank">Other</a></div>';

		load();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );

	it( 'announces a link a block creates after render', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-online-event-link">' +
			'<span class="gatherpress-online-event__text">Online event</span></div>';

		load();

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

		load();

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

		load();

		const link = document.querySelector( 'a' );

		link.appendChild( document.createTextNode( ' ' ) );

		await settle();

		expect( link.querySelectorAll( `.${ NOTICE_CLASS }` ) ).toHaveLength( 1 );
	} );

	it( 'waits for the document when the script runs during parsing', async () => {
		const readyState = jest
			.spyOn( document, 'readyState', 'get' )
			.mockReturnValue( 'loading' );

		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_blank">Site</a></div>';

		load();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();

		readyState.mockRestore();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		await settle();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).not.toBeNull();
	} );

	it( 'looks past other screen-reader text in the link', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-online-event-link">' +
			'<span class="gatherpress-online-event__text">Join</span></div>';

		load();

		const block = document.querySelector( '.wp-block-gatherpress-online-event-link' );
		const link = document.createElement( 'a' );

		// A tooltip puts its own screen-reader text inside the link.
		link.href = 'https://example.com/meet';
		link.target = '_blank';
		link.innerHTML =
			'Join<span class="gatherpress-tooltip">' +
			'<span class="screen-reader-text">Opens Zoom</span></span>';
		block.replaceChild( link, block.firstElementChild );

		await settle();

		expect( link.querySelectorAll( `.${ NOTICE_CLASS }` ) ).toHaveLength( 1 );
		expect( link.lastElementChild.className ).toBe(
			`screen-reader-text ${ NOTICE_CLASS }`
		);
	} );

	it( 'leaves the tooltip inside a link to its own text', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-online-event-link">' +
			'<a href="/x" target="_blank">Join' +
			'<span class="gatherpress-tooltip" data-gatherpress-tooltip="Opens Zoom">' +
			'</span></a></div>';

		load();

		await settle();
		initTooltips();

		const link = document.querySelector( 'a' );
		const tooltip = document.querySelector( '.gatherpress-tooltip' );

		expect( link.querySelectorAll( `.${ NOTICE_CLASS }` ) ).toHaveLength( 1 );
		expect(
			tooltip.querySelectorAll( ':scope > .screen-reader-text' )
		).toHaveLength( 1 );
		expect( tooltip.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );

	it( 'leaves a tooltip wrapping a link to its own text', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<span class="gatherpress-tooltip" data-gatherpress-tooltip="Our venue">' +
			'<a href="/x" target="_blank">Venue</a></span></div>';

		load();

		await settle();
		initTooltips();

		const tooltip = document.querySelector( '.gatherpress-tooltip' );

		expect(
			document.querySelector( 'a' ).querySelectorAll( `.${ NOTICE_CLASS }` )
		).toHaveLength( 1 );
		expect(
			tooltip.querySelector( ':scope > .screen-reader-text' ).textContent
		).toContain( 'Our venue' );
	} );

	it( 'announces an uppercase target', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_BLANK">Site</a></div>';

		load();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).not.toBeNull();

		const block = document.querySelector( '.wp-block-gatherpress-venue-detail' );
		const later = document.createElement( 'a' );

		later.href = '/y';
		later.setAttribute( 'target', '_Blank' );
		later.textContent = 'Later';
		block.appendChild( later );

		await settle();

		expect( later.querySelector( `.${ NOTICE_CLASS }` ) ).not.toBeNull();
	} );

	it( 'leaves a link that names another target alone', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">' +
			'<a href="/x" target="_self">Site</a></div>';

		load();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();

		const block = document.querySelector( '.wp-block-gatherpress-venue-detail' );
		const later = document.createElement( 'a' );

		later.href = '/y';
		later.target = 'namedwindow';
		later.textContent = 'Later';
		block.appendChild( later );

		await settle();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );

	it( 'copes with text added to a block', async () => {
		document.body.innerHTML =
			'<div class="wp-block-gatherpress-venue-detail">Venue</div>';

		load();

		const block = document.querySelector( '.wp-block-gatherpress-venue-detail' );

		block.appendChild( document.createTextNode( ' open daily' ) );

		await settle();

		expect( block.textContent ).toContain( 'open daily' );
	} );

	it( 'stands down when the page has no GatherPress blocks', () => {
		document.body.innerHTML = '<a href="/x" target="_blank">Site</a>';

		load();

		expect( document.querySelector( `.${ NOTICE_CLASS }` ) ).toBeNull();
	} );
} );
