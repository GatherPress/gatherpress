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
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/online-event-link/view';

/**
 * The screen-reader warning this block injects on new-tab links. Mirrors the
 * context payload from `render.php` so the client-side swap behaves like the
 * server render.
 */
const NEW_TAB_WARNING = ' (opens in a new tab)';

describe( 'online-event-link updateOnlineEventLink', () => {
	let state;
	let callbacks;

	beforeEach( () => {
		( { state, callbacks } = store( 'gatherpress' ) );

		// Reset the shared registry state between tests.
		delete state.posts;
	} );

	/**
	 * Builds the online event link wrapper markup and a fresh render target.
	 *
	 * @param {string} textMarkup Inner HTML of the `.gatherpress-online-event__text` element.
	 * @param {string} tagName    Whether the primary element starts as an anchor or a span.
	 * @return {HTMLElement} The wrapper element passed via `getElement().ref`.
	 */
	function setupElement( textMarkup, tagName = 'span' ) {
		const wrapper = document.createElement( 'div' );

		wrapper.innerHTML = `
			<div class="gatherpress-online-event__link" data-wp-interactive="gatherpress"
				data-wp-context='{ "postId": 123, "newTabWarning": "${ NEW_TAB_WARNING }" }'>
				<${ tagName } class="gatherpress-online-event__text">${ textMarkup }</${ tagName }>
			</div>
		`;

		return wrapper;
	}

	/**
	 * Runs the callback against the given element with a resolved post context.
	 *
	 * @param {HTMLElement} element The element to process.
	 * @param {Object}      context Optional context object; defaults to a post with id 123.
	 */
	function runCallback(
		element,
		context = { postId: 123, newTabWarning: NEW_TAB_WARNING }
	) {
		getContext.mockReturnValue( context );
		getElement.mockReturnValue( { ref: element } );
		callbacks.updateOnlineEventLink();
	}

	/**
	 * Seeds state with a live URL for the post so the callback reads it.
	 *
	 * @param {string} url The online event link to place in state.
	 */
	function seedStateLink( url ) {
		state.posts = { 123: { onlineEventLink: url } };
	}

	it( 'initializes state from the DOM link on first run without touching it', () => {
		// The DOM renders a working anchor; the first pass records its href
		// and leaves the markup alone so the server render stays authoritative.
		const element = setupElement( 'Join', 'a' );
		element.querySelector( 'a' ).href = 'https://meet.example.test/room';

		runCallback( element );

		const text = element.querySelector( '.gatherpress-online-event__text' );
		expect( text.tagName ).toBe( 'A' );
		expect( state.posts[ 123 ].onlineEventLink ).toBe(
			'https://meet.example.test/room'
		);
		// First run must not add a notice or swap the element.
		expect( element.querySelector( '.gatherpress-new-tab-notice' ) ).toBeNull();
	} );

	it( 'initializes state to an empty link on a first-run span without touching it', () => {
		// A fresh span first run must seed the empty link and leave the markup
		// alone so the server render stays authoritative.
		const element = setupElement( 'Join', 'span' );

		runCallback( element );

		const text = element.querySelector( '.gatherpress-online-event__text' );
		expect( text.tagName ).toBe( 'SPAN' );
		expect( state.posts[ 123 ].onlineEventLink ).toBe( '' );
		expect( element.querySelector( '.gatherpress-new-tab-notice' ) ).toBeNull();
	} );

	it( 'returns early when no post id is present in context', () => {
		// A payload without a post id must bail before any state or DOM work.
		runCallback( setupElement( 'Join' ), { postId: 0 } );

		expect( state.posts ).toBeUndefined();
	} );

	it( 'returns early when no online event text element exists', () => {
		const element = document.createElement( 'div' );

		runCallback( element );

		// initPostContext still seeds the post slot, but the missing text
		// element must stop the callback before any link state is written.
		expect( state.posts[ 123 ].onlineEventLink ).toBeUndefined();
	} );

	it( 'swaps a span to a link and keeps exactly one notice', () => {
		seedStateLink( 'https://meet.example.test/room' );
		// A stale notice (from a prior VM render) rides along in the span.
		const element = setupElement(
			`Join<span class="screen-reader-text gatherpress-new-tab-notice">${ NEW_TAB_WARNING }</span>`
		);

		runCallback( element );

		const link = element.querySelector( '.gatherpress-online-event__text' );
		expect( link.tagName ).toBe( 'A' );
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toBe( 'noopener noreferrer' );
		// Stale notice removed, fresh one appended once.
		expect(
			link.querySelectorAll( '.gatherpress-new-tab-notice' ).length
		).toBe( 1 );
		expect( link.textContent ).toContain( 'Join' );
		expect( link.textContent ).toContain( NEW_TAB_WARNING );
	} );

	it( 'swaps a link to plain text and drops the notice', () => {
		// An anchor is present but state no longer has a link.
		const element = setupElement(
			`Join<span class="screen-reader-text gatherpress-new-tab-notice">${ NEW_TAB_WARNING }</span>`,
			'a'
		);

		state.posts = { 123: { onlineEventLink: '' } };
		runCallback( element );

		const text = element.querySelector( '.gatherpress-online-event__text' );
		expect( text.tagName ).toBe( 'SPAN' );
		expect( text.querySelector( '.gatherpress-new-tab-notice' ) ).toBeNull();
		expect( text.textContent ).toContain( 'Join' );
	} );

	it( 'updates the href and keeps a single notice when the target link changes', () => {
		const element = setupElement(
			`Join<span class="screen-reader-text gatherpress-new-tab-notice">${ NEW_TAB_WARNING }</span>`,
			'a'
		);
		element.querySelector( 'a' ).href = 'https://meet.example.test/old';

		seedStateLink( 'https://meet.example.test/room' );
		runCallback( element );

		const link = element.querySelector( '.gatherpress-online-event__text' );
		expect( link.href ).toBe( 'https://meet.example.test/room' );
		expect(
			link.querySelectorAll( '.gatherpress-new-tab-notice' ).length
		).toBe( 1 );
		expect( link.textContent ).toContain( NEW_TAB_WARNING );
	} );

	it( 'keeps a single notice across repeated updates to the same href', () => {
		const element = setupElement(
			`Join<span class="screen-reader-text gatherpress-new-tab-notice">${ NEW_TAB_WARNING }</span>`,
			'a'
		);
		element.querySelector( 'a' ).href = 'https://meet.example.test/room';

		seedStateLink( 'https://meet.example.test/room' );
		runCallback( element );
		runCallback( element );

		const link = element.querySelector( '.gatherpress-online-event__text' );
		expect( link.href ).toBe( 'https://meet.example.test/room' );
		expect(
			link.querySelectorAll( '.gatherpress-new-tab-notice' ).length
		).toBe( 1 );
	} );

	it( 'does not append a notice on an existing link when the warning is missing', () => {
		// On the anchor-stays-anchor path, a payload without newTabWarning
		// must leave the href in place and add no empty notice span.
		seedStateLink( 'https://meet.example.test/room' );
		const element = setupElement( 'Join', 'a' );
		element.querySelector( 'a' ).href = 'https://meet.example.test/room';

		runCallback( element, { postId: 123 } );

		const link = element.querySelector( '.gatherpress-online-event__text' );
		expect( link.tagName ).toBe( 'A' );
		expect( link.href ).toBe( 'https://meet.example.test/room' );
		expect( link.querySelector( '.gatherpress-new-tab-notice' ) ).toBeNull();
	} );

	it( 'does not throw when the new-tab warning is missing from context', () => {
		seedStateLink( 'https://meet.example.test/room' );
		const element = setupElement( 'Join' );

		// Context without `newTabWarning` mirrors a payload built before the
		// key was added; the swap must still produce a usable link.
		runCallback( element, { postId: 123 } );

		const link = element.querySelector( '.gatherpress-online-event__text' );
		expect( link.tagName ).toBe( 'A' );
		expect( link.target ).toBe( '_blank' );
	} );
} );
