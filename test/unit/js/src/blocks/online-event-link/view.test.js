/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = { state: { posts: {} }, actions: {}, callbacks: {} };
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

jest.mock(
	'@src/helpers/interactivity',
	() => ( {
		initPostContext: jest.fn( ( state, postId ) => {
			state.posts = state.posts ?? {};
			if ( postId && ! state.posts[ postId ] ) {
				state.posts[ postId ] = {};
			}
		} ),
	} )
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/online-event-link/view';

describe( 'online-event-link updateOnlineEventLink', () => {
	let callbacks;
	let registry;
	let container;

	beforeEach( () => {
		registry = store( 'gatherpress' );
		registry.state.posts = {};
		callbacks = registry.callbacks;

		container = document.createElement( 'div' );
		getElement.mockReturnValue( { ref: container } );
		getContext.mockReturnValue( {
			postId: 42,
			i18n: { opensInNewTab: '(opens in a new tab)' },
		} );
	} );

	it( 'returns early when postId is missing', () => {
		getContext.mockReturnValue( {} );
		callbacks.updateOnlineEventLink();
		expect( registry.state.posts[ 42 ] ).toBeUndefined();
	} );

	it( 'returns early when currentElement is not found', () => {
		callbacks.updateOnlineEventLink();
		expect( registry.state.posts[ 42 ] ).toBeDefined();
	} );

	it( 'initializes state from DOM on first run and leaves DOM unchanged', () => {
		const span = document.createElement( 'span' );
		span.className = 'gatherpress-online-event__text';
		span.textContent = 'Event Link Placeholder';
		container.appendChild( span );

		callbacks.updateOnlineEventLink();

		expect( registry.state.posts[ 42 ].onlineEventLink ).toBe( '' );
		expect( container.querySelector( 'span.gatherpress-online-event__text' ) ).toBe( span );
		expect( container.querySelector( 'a' ) ).toBeNull();
	} );

	it( 'initializes state with href on first run when element is already a link', () => {
		const link = document.createElement( 'a' );
		link.className = 'gatherpress-online-event__text';
		link.href = 'https://example.com/stream';
		link.textContent = 'Watch Stream';
		container.appendChild( link );

		callbacks.updateOnlineEventLink();

		expect( registry.state.posts[ 42 ].onlineEventLink ).toBe( 'https://example.com/stream' );
		expect( container.querySelector( 'a.gatherpress-online-event__text' ) ).toBe( link );
	} );

	it( 'swaps span to anchor with screen-reader text when onlineEventLink is provided', () => {
		const span = document.createElement( 'span' );
		span.className = 'gatherpress-online-event__text';
		span.textContent = 'Join Meeting';
		container.appendChild( span );

		// Simulate first run.
		callbacks.updateOnlineEventLink();

		// Now simulate state change from RSVP response.
		registry.state.posts[ 42 ].onlineEventLink = 'https://meet.example.com/room1';
		callbacks.updateOnlineEventLink();

		const link = container.querySelector( 'a.gatherpress-online-event__text' );
		expect( link ).not.toBeNull();
		expect( link.href ).toBe( 'https://meet.example.com/room1' );
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toBe( 'noopener noreferrer' );

		const srText = link.querySelector( '.screen-reader-text' );
		expect( srText ).not.toBeNull();
		expect( srText.textContent ).toBe( ' (opens in a new tab)' );
	} );

	it( 'does not duplicate screen-reader-text if already present in inner HTML', () => {
		const span = document.createElement( 'span' );
		span.className = 'gatherpress-online-event__text';
		span.innerHTML = 'Join Meeting<span class="screen-reader-text"> (opens in a new tab)</span>';
		container.appendChild( span );

		callbacks.updateOnlineEventLink();

		registry.state.posts[ 42 ].onlineEventLink = 'https://meet.example.com/room1';
		callbacks.updateOnlineEventLink();

		const link = container.querySelector( 'a.gatherpress-online-event__text' );
		const srSpans = link.querySelectorAll( '.screen-reader-text' );
		expect( srSpans ).toHaveLength( 1 );
	} );

	it( 'swaps anchor back to span and strips screen-reader text when onlineEventLink becomes empty', () => {
		const link = document.createElement( 'a' );
		link.className = 'gatherpress-online-event__text';
		link.href = 'https://meet.example.com/room1';
		link.innerHTML = 'Join Meeting<span class="screen-reader-text"> (opens in a new tab)</span>';
		container.appendChild( link );

		callbacks.updateOnlineEventLink();

		// State becomes empty (e.g. user cancelled RSVP).
		registry.state.posts[ 42 ].onlineEventLink = '';
		callbacks.updateOnlineEventLink();

		const span = container.querySelector( 'span.gatherpress-online-event__text' );
		expect( span ).not.toBeNull();
		expect( span.querySelector( '.screen-reader-text' ) ).toBeNull();
		expect( span.textContent.trim() ).toBe( 'Join Meeting' );
	} );

	it( 'uses default fallback text if context.i18n.opensInNewTab is not defined', () => {
		getContext.mockReturnValue( { postId: 42 } );
		const span = document.createElement( 'span' );
		span.className = 'gatherpress-online-event__text';
		span.textContent = 'Join Meeting';
		container.appendChild( span );

		callbacks.updateOnlineEventLink();

		registry.state.posts[ 42 ].onlineEventLink = 'https://meet.example.com/room1';
		callbacks.updateOnlineEventLink();

		const link = container.querySelector( 'a.gatherpress-online-event__text' );
		const srText = link.querySelector( '.screen-reader-text' );
		expect( srText.textContent ).toBe( ' (opens in a new tab)' );
	} );

	it( 'leaves anchor untouched when onlineEventLink matches current href', () => {
		const link = document.createElement( 'a' );
		link.className = 'gatherpress-online-event__text';
		link.href = 'https://meet.example.com/room1';
		link.textContent = 'Join Meeting';
		container.appendChild( link );

		callbacks.updateOnlineEventLink();

		// onlineEventLink matches existing href
		registry.state.posts[ 42 ].onlineEventLink = 'https://meet.example.com/room1';
		callbacks.updateOnlineEventLink();

		expect( link.href ).toBe( 'https://meet.example.com/room1' );
	} );

	it( 'updates anchor href when onlineEventLink URL changes', () => {
		const link = document.createElement( 'a' );
		link.className = 'gatherpress-online-event__text';
		link.href = 'https://meet.example.com/room1';
		link.textContent = 'Join Meeting';
		container.appendChild( link );

		callbacks.updateOnlineEventLink();

		registry.state.posts[ 42 ].onlineEventLink = 'https://meet.example.com/room2';
		callbacks.updateOnlineEventLink();

		expect( link.href ).toBe( 'https://meet.example.com/room2' );
	} );
} );
