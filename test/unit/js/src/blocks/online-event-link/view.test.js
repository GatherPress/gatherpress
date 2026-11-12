/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace shares one registry,
 * mirroring the real runtime.
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = {
						state: {},
						actions: {},
						callbacks: {},
					};
				}

				const registry = registries[ name ];

				Object.assign( registry.state, config.state );
				Object.assign( registry.actions, config.actions );
				Object.assign( registry.callbacks, config.callbacks );

				return registry;
			},
			getElement: jest.fn(),
			getContext: jest.fn(),
		};
	},
	{ virtual: true },
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { removeTooltips } from '@src/blocks/online-event-link/view';

/**
 * The link text the online-event block template saves.
 *
 * @type {string}
 */
const TOOLTIP_TEXT =
	'<span class="gatherpress-tooltip" data-gatherpress-tooltip="Link available for attendees only.">Online event</span>';

/**
 * The same text after the tooltip script ran on it in the browser.
 *
 * @type {string}
 */
const INITIALIZED_TOOLTIP_TEXT =
	'<span class="gatherpress-tooltip gatherpress-tooltip--is-active" data-gatherpress-tooltip="Link available for attendees only." data-gatherpress-tooltip-text-color="#fff" data-gatherpress-tooltip-bg-color="#000" tabindex="0">Online event<span class="screen-reader-text gatherpress--screen-reader-text gatherpress-tooltip-notice"> (Link available for attendees only.)</span></span>';

/**
 * Builds the online-event-link block wrapper.
 *
 * @param {string} inner The markup inside the wrapper.
 *
 * @return {HTMLElement} The wrapper element.
 */
function setupDom( inner ) {
	document.body.innerHTML = `<div class="gatherpress-online-event__link">${ inner }</div>`;

	return document.querySelector( '.gatherpress-online-event__link' );
}

describe( 'online-event-link removeTooltips', () => {
	it( 'removes the tooltip, its screen-reader note and its tab stop, and keeps the text', () => {
		const wrapper = setupDom( `<a href="#">${ INITIALIZED_TOOLTIP_TEXT }</a>` );
		const link = wrapper.querySelector( 'a' );

		removeTooltips( link );

		expect( link.textContent ).toBe( 'Online event' );
		expect( link.querySelector( '.gatherpress-tooltip' ) ).toBeNull();
		expect( link.querySelector( '[data-gatherpress-tooltip]' ) ).toBeNull();
		expect( link.querySelector( '[tabindex]' ) ).toBeNull();

		const span = link.querySelector( 'span' );

		expect( span.hasAttribute( 'class' ) ).toBe( false );
		expect( span.hasAttribute( 'data-gatherpress-tooltip-text-color' ) ).toBe( false );
		expect( span.hasAttribute( 'data-gatherpress-tooltip-bg-color' ) ).toBe( false );
	} );

	it( 'keeps the other classes of a tooltip element', () => {
		const wrapper = setupDom(
			'<a href="#"><span class="gatherpress-tooltip highlight" data-gatherpress-tooltip="Note">Online event</span></a>',
		);
		const link = wrapper.querySelector( 'a' );

		removeTooltips( link );

		expect( link.innerHTML ).toBe(
			'<span class="highlight">Online event</span>',
		);
	} );

	it( 'leaves text without a tooltip unchanged', () => {
		const wrapper = setupDom( '<a href="#"><strong>Join</strong> the call</a>' );
		const link = wrapper.querySelector( 'a' );

		removeTooltips( link );

		expect( link.innerHTML ).toBe( '<strong>Join</strong> the call' );
	} );
} );

describe( 'online-event-link updateOnlineEventLink', () => {
	const POST_ID = 25;
	const MEETING_URL = 'https://meet.example.test/qa-room';
	let state;
	let callbacks;

	/**
	 * Points the mocked Interactivity API at the wrapper and the context.
	 *
	 * @param {HTMLElement} wrapper The block wrapper.
	 * @param {Object}      context The block context.
	 *
	 * @return {void}
	 */
	function mockBlock( wrapper, context ) {
		getElement.mockReturnValue( { ref: wrapper } );
		getContext.mockReturnValue( context );
	}

	/**
	 * Runs the callback the way the runtime does: once to read the server
	 * markup, then again after the RSVP state changes.
	 *
	 * @param {string} link The online event link the RSVP response sets.
	 *
	 * @return {void}
	 */
	function runWithLink( link ) {
		callbacks.updateOnlineEventLink();
		state.posts[ POST_ID ].onlineEventLink = link;
		callbacks.updateOnlineEventLink();
	}

	beforeEach( () => {
		( { state, callbacks } = store( 'gatherpress' ) );

		delete state.posts;
		getElement.mockReset();
		getContext.mockReset();
	} );

	it( 'gives a new link the text without the attendees-only note', () => {
		const wrapper = setupDom(
			`<span class="gatherpress-online-event__text">${ INITIALIZED_TOOLTIP_TEXT }</span>`,
		);
		mockBlock( wrapper, { postId: POST_ID, linkText: TOOLTIP_TEXT } );

		runWithLink( MEETING_URL );

		const link = wrapper.querySelector( 'a.gatherpress-online-event__text' );

		expect( link ).not.toBeNull();
		expect( link.getAttribute( 'href' ) ).toBe( MEETING_URL );
		expect( link.textContent ).toBe( 'Online event' );
		expect( link.querySelector( '[data-gatherpress-tooltip]' ) ).toBeNull();
		expect( link.querySelector( '[tabindex]' ) ).toBeNull();
		expect( link.innerHTML ).toBe( '<span>Online event</span>' );
	} );

	it( 'falls back to the current markup when the context has no link text', () => {
		const wrapper = setupDom(
			`<span class="gatherpress-online-event__text">${ INITIALIZED_TOOLTIP_TEXT }</span>`,
		);
		mockBlock( wrapper, { postId: POST_ID } );

		runWithLink( MEETING_URL );

		const link = wrapper.querySelector( 'a.gatherpress-online-event__text' );

		expect( link.textContent ).toBe( 'Online event' );
		expect( link.querySelector( '.gatherpress-tooltip-notice' ) ).toBeNull();
	} );

	it( 'gives the attendees-only note back when the link goes away', () => {
		const wrapper = setupDom(
			`<a class="gatherpress-online-event__text" href="${ MEETING_URL }"><span>Online event</span></a>`,
		);
		mockBlock( wrapper, { postId: POST_ID, linkText: TOOLTIP_TEXT } );

		runWithLink( '' );

		const text = wrapper.querySelector( '.gatherpress-online-event__text' );

		expect( text.tagName ).toBe( 'SPAN' );
		expect( text.innerHTML ).toBe( TOOLTIP_TEXT );
	} );

	it( 'updates the address of a link that stays a link', () => {
		const wrapper = setupDom(
			`<a class="gatherpress-online-event__text" href="${ MEETING_URL }">Online event</a>`,
		);
		mockBlock( wrapper, { postId: POST_ID, linkText: 'Online event' } );

		runWithLink( 'https://meet.example.test/other-room' );

		expect(
			wrapper.querySelector( 'a' ).getAttribute( 'href' ),
		).toBe( 'https://meet.example.test/other-room' );
	} );

	it( 'leaves plain text alone while there is still no link', () => {
		const wrapper = setupDom(
			`<span class="gatherpress-online-event__text">${ TOOLTIP_TEXT }</span>`,
		);
		mockBlock( wrapper, { postId: POST_ID, linkText: TOOLTIP_TEXT } );

		runWithLink( '' );

		expect( wrapper.querySelector( 'a' ) ).toBeNull();
		expect(
			wrapper.querySelector( '[data-gatherpress-tooltip]' ),
		).not.toBeNull();
	} );

	it( 'does nothing without a post ID or without the text element', () => {
		const wrapper = setupDom( '' );

		mockBlock( wrapper, undefined );
		callbacks.updateOnlineEventLink();
		mockBlock( wrapper, {} );
		callbacks.updateOnlineEventLink();

		expect( state.posts ).toBeUndefined();

		mockBlock( wrapper, { postId: POST_ID } );
		callbacks.updateOnlineEventLink();

		expect( state.posts[ POST_ID ].onlineEventLink ).toBeUndefined();
	} );
} );
