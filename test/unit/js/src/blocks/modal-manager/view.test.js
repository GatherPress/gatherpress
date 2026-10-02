/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace (modal-manager,
 * helpers) shares one registry, mirroring the real runtime.
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
import { store } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
// Import the actual module so its actions register on the shared store.
import '@src/blocks/modal-manager/view';

/**
 * Regression coverage for #1719 — openModal must not throw when it is
 * called with neither an event nor an element (e.g. a `querySelector`
 * miss at the call site used to produce `openModal( null, null )` and
 * a "Cannot read properties of null (reading 'target')" TypeError).
 */
describe( 'modal-manager openModal', () => {
	let actions;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );
		document.body.innerHTML = '';
	} );

	it( 'bails without throwing when called with no event and no element (#1719)', () => {
		expect( () => actions.openModal( null, null ) ).not.toThrow();
	} );

	it( 'falls back to event.target when no element is given', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<div class="wp-block-gatherpress-modal"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const event = { preventDefault: jest.fn(), target: button };

		actions.openModal( event );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( true );
	} );

	it( 'uses the explicit element when one is given', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<div class="wp-block-gatherpress-modal"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );

		actions.openModal( null, button );

		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( true );
	} );

	it( 'openModalOnEnter opens the modal on Enter or Space key', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<div class="wp-block-gatherpress-modal"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const enterEvent = { key: 'Enter', preventDefault: jest.fn(), target: button };
		const spaceEvent = { key: ' ', preventDefault: jest.fn(), target: button };
		const tabEvent = { key: 'Tab', preventDefault: jest.fn(), target: button };

		// Other keys should not open the modal.
		actions.openModalOnEnter( tabEvent );
		expect( tabEvent.preventDefault ).not.toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( false );

		// Enter should open the modal.
		actions.openModalOnEnter( enterEvent );
		expect( enterEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( true );

		// Reset visibility.
		document.querySelector( '.wp-block-gatherpress-modal' ).classList.remove( 'gatherpress--is-visible' );

		// Space should open the modal.
		actions.openModalOnEnter( spaceEvent );
		expect( spaceEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( true );
	} );

	it( 'closeModalOnEnter closes the modal on Enter or Space key', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Close</button>
				<div class="wp-block-gatherpress-modal gatherpress--is-visible"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const enterEvent = { key: 'Enter', preventDefault: jest.fn(), target: button };
		const spaceEvent = { key: ' ', preventDefault: jest.fn(), target: button };
		const tabEvent = { key: 'Tab', preventDefault: jest.fn(), target: button };

		// Other keys should not close the modal.
		actions.closeModalOnEnter( tabEvent );
		expect( tabEvent.preventDefault ).not.toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( true );

		// Enter should close the modal.
		actions.closeModalOnEnter( enterEvent );
		expect( enterEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( false );

		// Reset visibility.
		document.querySelector( '.wp-block-gatherpress-modal' ).classList.add( 'gatherpress--is-visible' );

		// Space should close the modal.
		actions.closeModalOnEnter( spaceEvent );
		expect( spaceEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' ),
		).toBe( false );
	} );
} );

/**
 * closeModal must hand focus back to whatever opened the modal. The
 * trigger class often sits on a block wrapper (e.g. the Event Date
 * block's <div>) with the focusable link inside it; focusing the
 * wrapper itself does nothing and drops focus to the page body.
 */
describe( 'modal-manager closeModal focus return', () => {
	let actions;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );
		document.body.innerHTML = '';
	} );

	it( 'focuses a link nested inside the trigger wrapper', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<div class="gatherpress-modal--trigger-open">
					<a href="/event/" role="button">9:00</a>
				</div>
				<div class="wp-block-gatherpress-modal gatherpress--is-visible">
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</div>
			</div>
		`;

		actions.closeModal( null, document.querySelector( '.close' ) );

		expect( document.activeElement ).toBe(
			document.querySelector( '.gatherpress-modal--trigger-open a' ),
		);
	} );

	it( 'focuses a link without an href that the server made focusable', () => {
		// A Button block with no URL renders <a> without href; the server
		// adds tabindex="0" so it can take focus.
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<div class="wp-block-button gatherpress-modal--trigger-open">
					<a class="wp-block-button__link" role="button" tabindex="0">Open</a>
				</div>
				<div class="wp-block-gatherpress-modal gatherpress--is-visible">
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</div>
			</div>
		`;

		actions.closeModal( null, document.querySelector( '.close' ) );

		expect( document.activeElement ).toBe(
			document.querySelector( '.gatherpress-modal--trigger-open a' ),
		);
	} );

	it( 'still focuses a button nested inside the trigger wrapper', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<div class="gatherpress-modal--trigger-open">
					<button type="button">Open</button>
				</div>
				<div class="wp-block-gatherpress-modal gatherpress--is-visible">
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</div>
			</div>
		`;

		actions.closeModal( null, document.querySelector( '.close' ) );

		expect( document.activeElement ).toBe(
			document.querySelector( '.gatherpress-modal--trigger-open button' ),
		);
	} );

	it( 'focuses the trigger itself when it is the link', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<a href="/event/" class="gatherpress-modal--trigger-open" role="button">Open</a>
				<div class="wp-block-gatherpress-modal gatherpress--is-visible">
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</div>
			</div>
		`;

		actions.closeModal( null, document.querySelector( '.close' ) );

		expect( document.activeElement ).toBe(
			document.querySelector( 'a.gatherpress-modal--trigger-open' ),
		);
	} );
} );

/**
 * An unnamed modal (server marker `data-gatherpress-default-label`) is
 * named after its first heading the user can see when it opens. An RSVP
 * modal without a custom name keeps hidden state headings (e.g. "Thank
 * you for your RSVP!") before the form, so the first heading in the
 * markup is not enough.
 */
describe( 'modal-manager openModal naming', () => {
	let actions;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );
		document.body.innerHTML = '';
	} );

	const render = ( modalAttrs, content ) => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button" class="open">Open</button>
				<div class="wp-block-gatherpress-modal" role="dialog" aria-label="Modal" ${ modalAttrs }>
					<div class="wp-block-gatherpress-modal-content">${ content }</div>
				</div>
			</div>
		`;
		actions.openModal( null, document.querySelector( '.open' ) );
		return document.querySelector( '[role="dialog"]' );
	};

	it( 'skips hidden headings and uses the first visible one', () => {
		const modal = render(
			'data-gatherpress-default-label="true"',
			`<div style="display: none"><h3>Thank you for your RSVP!</h3></div>
			<div hidden><h3>This event has already occurred.</h3></div>
			<div class="gatherpress--is-hidden"><h3>Hidden by class</h3></div>
			<h2>Register for this event</h2>`,
		);

		const heading = document.querySelector( 'h2' );
		expect( heading.id ).toMatch( /^gatherpress-modal-heading-\d+$/ );
		expect( modal.getAttribute( 'aria-labelledby' ) ).toBe( heading.id );
	} );

	it( 'reuses an existing heading id', () => {
		const modal = render(
			'data-gatherpress-default-label="true"',
			'<h3 id="event-title">QA Morning Coffee</h3>',
		);

		expect( modal.getAttribute( 'aria-labelledby' ) ).toBe( 'event-title' );
	} );

	it( 'keeps the generic label when no heading is visible', () => {
		const modal = render(
			'data-gatherpress-default-label="true" aria-labelledby="stale-id"',
			'<div style="display: none"><h3>Hidden</h3></div><p>No heading</p>',
		);

		expect( modal.hasAttribute( 'aria-labelledby' ) ).toBe( false );
		expect( modal.getAttribute( 'aria-label' ) ).toBe( 'Modal' );
	} );

	it( 'leaves a modal with a custom name alone', () => {
		const modal = render( '', '<h3>Heading</h3>' );

		expect( modal.hasAttribute( 'aria-labelledby' ) ).toBe( false );
		expect( document.querySelector( 'h3' ).id ).toBe( '' );
	} );
} );
