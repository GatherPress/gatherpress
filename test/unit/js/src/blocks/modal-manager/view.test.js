/**
 * External dependencies
 */
import { afterEach, describe, expect, it, jest, beforeEach } from '@jest/globals';

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
			withSyncEvent: jest.fn( ( fn ) => {
				fn.sync = true;
				return fn;
			} ),
		};
	},
	{ virtual: true },
);

/**
 * WordPress dependencies
 */
import { getElement, store, withSyncEvent } from '@wordpress/interactivity';

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

	it( 'wraps synchronous actions in withSyncEvent to prevent WordPress 6.8+ deprecation warnings (#2388)', () => {
		expect( withSyncEvent ).toHaveBeenCalledWith( expect.any( Function ) );
		expect( actions.openModal.sync ).toBe( true );
		expect( actions.openModalOnEnter.sync ).toBe( true );
		expect( actions.closeModal.sync ).toBe( true );
		expect( actions.closeModalOnEnter.sync ).toBe( true );
		expect( actions.closeModalOnOverlay.sync ).toBe( true );
	} );

	it( 'falls back to event.target when no element is given', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<dialog class="wp-block-gatherpress-modal"></dialog>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const event = { preventDefault: jest.fn(), target: button };

		actions.openModal( event );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( true );
	} );

	it( 'uses the explicit element when one is given', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<dialog class="wp-block-gatherpress-modal"></dialog>
			</div>
		`;

		const button = document.querySelector( 'button' );

		actions.openModal( null, button );

		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( true );
	} );

	it( 'openModalOnEnter opens the modal on Enter or Space key', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<dialog class="wp-block-gatherpress-modal"></dialog>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const enterEvent = { key: 'Enter', preventDefault: jest.fn(), target: button };
		const spaceEvent = { key: ' ', preventDefault: jest.fn(), target: button };
		const tabEvent = { key: 'Tab', preventDefault: jest.fn(), target: button };

		// Other keys should not open the modal.
		actions.openModalOnEnter( tabEvent );
		expect( tabEvent.preventDefault ).not.toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( false );

		// Enter should open the modal.
		actions.openModalOnEnter( enterEvent );
		expect( enterEvent.preventDefault ).toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( true );

		// Reset visibility.
		document.querySelector( '.wp-block-gatherpress-modal' ).close();

		// Space should open the modal.
		actions.openModalOnEnter( spaceEvent );
		expect( spaceEvent.preventDefault ).toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( true );
	} );

	it( 'closeModalOnEnter closes the modal on Enter or Space key', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Close</button>
				<dialog class="wp-block-gatherpress-modal" open></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const enterEvent = { key: 'Enter', preventDefault: jest.fn(), target: button };
		const spaceEvent = { key: ' ', preventDefault: jest.fn(), target: button };
		const tabEvent = { key: 'Tab', preventDefault: jest.fn(), target: button };

		// Other keys should not close the modal.
		actions.closeModalOnEnter( tabEvent );
		expect( tabEvent.preventDefault ).not.toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( true );

		// Enter should close the modal.
		actions.closeModalOnEnter( enterEvent );
		expect( enterEvent.preventDefault ).toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( false );

		// Reset visibility.
		document.querySelector( '.wp-block-gatherpress-modal' ).setAttribute( 'open', '' );

		// Space should close the modal.
		actions.closeModalOnEnter( spaceEvent );
		expect( spaceEvent.preventDefault ).toHaveBeenCalled();
		expect( document.querySelector( '.wp-block-gatherpress-modal' ).open ).toBe( false );
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
				<dialog class="wp-block-gatherpress-modal" open>
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</dialog>
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
				<dialog class="wp-block-gatherpress-modal" open>
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</dialog>
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
				<dialog class="wp-block-gatherpress-modal" open>
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</dialog>
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
				<dialog class="wp-block-gatherpress-modal" open>
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</dialog>
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
				<dialog class="wp-block-gatherpress-modal" aria-label="Modal" ${ modalAttrs }>
					<div class="wp-block-gatherpress-modal-content">${ content }</div>
				</dialog>
			</div>
		`;
		actions.openModal( null, document.querySelector( '.open' ) );
		return document.querySelector( 'dialog' );
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

/**
 * The modal is a <dialog>: showModal() opens it as a modal, and Escape
 * (the dialog's cancel event) and a click on its overlay close it through
 * closeModal, so focus returns the way a close button returns it.
 */
describe( 'modal-manager dialog', () => {
	let actions;

	const render = ( modalAttrs = '' ) => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button" class="gatherpress-modal--trigger-open">Open</button>
				<dialog class="wp-block-gatherpress-modal" ${ modalAttrs }>
					<div class="wp-block-gatherpress-modal-content">
						<button type="button" class="close">Close</button>
					</div>
				</dialog>
			</div>
		`;

		return document.querySelector( 'dialog' );
	};

	let showModal;
	let close;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );
		showModal = jest.spyOn( window.HTMLDialogElement.prototype, 'showModal' );
		close = jest.spyOn( window.HTMLDialogElement.prototype, 'close' );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'opens the modal with showModal(), which focuses its first control', () => {
		const modal = render();

		actions.openModal( null, document.querySelector( '.gatherpress-modal--trigger-open' ) );

		expect( showModal ).toHaveBeenCalledTimes( 1 );
		expect( modal.open ).toBe( true );
		expect( document.activeElement ).toBe( document.querySelector( '.close' ) );
	} );

	it( 'leaves a modal that is already open alone', () => {
		render( 'open' );

		actions.openModal( null, document.querySelector( '.gatherpress-modal--trigger-open' ) );

		expect( showModal ).not.toHaveBeenCalled();
	} );

	it( 'does not close a modal that is already closed', () => {
		render();

		actions.closeModal( null, document.querySelector( '.close' ) );

		expect( close ).not.toHaveBeenCalled();
	} );

	it( 'closes on Escape through the cancel event and returns focus to the trigger', () => {
		const modal = render( 'open' );
		const event = { preventDefault: jest.fn(), target: modal };

		actions.closeModal( event );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect( modal.open ).toBe( false );
		expect( document.activeElement ).toBe(
			document.querySelector( '.gatherpress-modal--trigger-open' ),
		);
	} );

	it( 'closes on a click on the overlay', () => {
		const modal = render( 'open' );
		const event = { preventDefault: jest.fn(), target: modal, currentTarget: modal };

		actions.closeModalOnOverlay( event );

		expect( modal.open ).toBe( false );
	} );

	it( 'stays open on a click inside the content', () => {
		const modal = render( 'open' );
		const event = {
			preventDefault: jest.fn(),
			target: document.querySelector( '.close' ),
			currentTarget: modal,
		};

		actions.closeModalOnOverlay( event );

		expect( event.preventDefault ).not.toHaveBeenCalled();
		expect( modal.open ).toBe( true );
	} );
} );

/**
 * Without JavaScript, the server opens a modal a reload asks for with the
 * `open` attribute, which leaves it non-modal. With JavaScript, the view
 * script takes it over and reopens it as a modal dialog.
 */
describe( 'modal-manager takeOverOpenedModal', () => {
	let callbacks;
	let showModal;

	const render = ( containerClass = '', modalAttrs = 'open data-gatherpress-opened-on-load="true"' ) => {
		document.body.innerHTML = `
			<div class="${ containerClass }">
				<div class="wp-block-gatherpress-modal-manager">
					<button type="button" class="gatherpress-modal--trigger-open">Open</button>
					<dialog class="wp-block-gatherpress-modal" ${ modalAttrs }>
						<button type="submit" class="gatherpress-modal__backdrop" tabindex="-1" aria-hidden="true"></button>
						<div class="wp-block-gatherpress-modal-content">
							<button type="button" class="close">Close</button>
						</div>
					</dialog>
				</div>
			</div>
		`;

		const modal = document.querySelector( 'dialog' );

		getElement.mockReturnValue( { ref: modal } );

		return modal;
	};

	beforeEach( () => {
		jest.useFakeTimers();
		( { callbacks } = store( 'gatherpress' ) );
		showModal = jest.spyOn( window.HTMLDialogElement.prototype, 'showModal' );
		window.history.replaceState( null, '', '/event/?gatherpress_modal=42-1&ref=x#gatherpress-modal-manager-42-1' );
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.restoreAllMocks();
		window.history.replaceState( null, '', '/' );
	} );

	it( 'reopens the modal with showModal() once hydration is done, and steps the overlay button aside', () => {
		const modal = render();

		callbacks.takeOverOpenedModal();

		// Hydration would move a dialog made modal right away out of the top layer.
		expect( showModal ).not.toHaveBeenCalled();

		jest.runAllTimers();

		expect( showModal ).toHaveBeenCalledTimes( 1 );
		expect( modal.open ).toBe( true );
		expect( document.querySelector( '.gatherpress-modal__backdrop' ).hidden ).toBe( true );
		expect( document.activeElement ).toBe( document.querySelector( '.close' ) );
	} );

	it( 'drops the query arg so a reload does not open the modal again', () => {
		render();

		callbacks.takeOverOpenedModal();

		expect( window.location.search ).toBe( '?ref=x' );
		expect( window.location.hash ).toBe( '#gatherpress-modal-manager-42-1' );
	} );

	it( 'leaves a modal the server did not open alone', () => {
		render( '', '' );

		callbacks.takeOverOpenedModal();

		expect( showModal ).not.toHaveBeenCalled();
		expect( window.location.search ).toBe( '?gatherpress_modal=42-1&ref=x' );
	} );

	it( 'leaves a modal inside a hidden RSVP status as the server rendered it', () => {
		const modal = render( 'gatherpress--is-hidden' );

		callbacks.takeOverOpenedModal();

		expect( showModal ).not.toHaveBeenCalled();
		expect( modal.open ).toBe( true );
	} );

	it( 'works without a no-JavaScript overlay button', () => {
		const modal = render();

		document.querySelector( '.gatherpress-modal__backdrop' ).remove();
		callbacks.takeOverOpenedModal();
		jest.runAllTimers();

		expect( modal.open ).toBe( true );
		expect( showModal ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not reopen a modal that closed before the next task', () => {
		const modal = render();

		callbacks.takeOverOpenedModal();
		modal.close();
		jest.runAllTimers();

		expect( showModal ).not.toHaveBeenCalled();
		expect( modal.open ).toBe( false );
	} );
} );
